<?php
// Set the JSON header
header("Content-type: text/json");

include("uptimeDB.php");


if (isset($_GET['call'])){
	// getAllGroup
	$query_type = $_GET['call'];
}
if (isset($_GET['groupId'])){
	$groupId = $_GET['groupId'];
}
if (isset($_GET['topOrBottom'])){
	// top|bottom
	$topOrBottom = $_GET['topOrBottom'];
	if ($topOrBottom == "top") {
		$sortOrder = "DESC";
	} else {
		$sortOrder = "ASC";
	}
}
if (isset($_GET['numElements'])){
	$numElements = $_GET['numElements'];
}
if (isset($_GET['aggregateFn'])){
	// average|max|min
	$aggregateFn = $_GET['aggregateFn'];
}
if (isset($_GET['timePeriod'])){
	// In hours:
	// 1|3|6|12|24
	$timePeriod = $_GET['timePeriod'];
	$time_frame = $timePeriod * 60 * 60;
}
if (isset($_GET['metric'])){
	// 
	$metric = $_GET['metric'];
}

	
$db = new uptimeDB;
if ($db->connectDB())
{
	if ($query_type == "getAllGroup") {
		$sql = "select * from entity_group";
		$result = $db->execQuery($sql);
		echo json_encode($result);
	}
	if($query_type == "listMetrics") {
		$sql = "select distinct erp.ERDC_PARAMETER_ID as erdc_param, eb.name, ep.short_description as short_desc, ep.units, ep.data_type_id
				from erdc_retained_parameter erp
				join erdc_configuration ec on erp.configuration_id = ec.id
				join erdc_base eb on ec.erdc_base_id = eb.erdc_base_id
				join erdc_parameter ep on ep.erdc_parameter_id = erp.erdc_parameter_id
				join erdc_instance ei on ec.id = ei.configuration_id 
				where ei.entity_id is not null
				order by eb.name, ep.short_description";
		$result = $db->execQuery($sql);
		
		$returnArray = array();
		// SNMP Poller, for some reason, has some random number appended to the name in erdc_base.  Remove those numbers before showing in dropdown.
		foreach($result as $row) {
			if (preg_match("/SNMP Poller/i",$row[NAME])) {
				array_push($returnArray, array("ERDC_PARAM"=>$row[ERDC_PARAM], "NAME"=>"SNMP Poller", "SHORT_DESC"=>$row[SHORT_DESC], "UNITS"=>$row[UNITS], "DATA_TYPE_ID"=>$row[DATA_TYPE_ID]));
			} else {
				array_push($returnArray, array("ERDC_PARAM"=>$row[ERDC_PARAM], "NAME"=>$row[NAME], "SHORT_DESC"=>$row[SHORT_DESC], "UNITS"=>$row[UNITS], "DATA_TYPE_ID"=>$row[DATA_TYPE_ID]));
			}
		}
		echo json_encode($returnArray);
	}

	
	if ($query_type == "getMetrics") {
		$groupIdString = getChildGroups($db, $groupId);		
		$returnArray = array();
		
		if ($metric == "cpu") {
			if ($db->dbType == "mysql") {
				if ($aggregateFn == "average") {
					$sql = "Select eps.entity_id, e.display_name as name, avg(eps.cpu_usage_total) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				} elseif ($aggregateFn == "max") {
					$sql = "Select eps.entity_id, e.display_name as name, max(eps.cpu_usage_total) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				} elseif ($aggregateFn == "min") {
					$sql = "Select eps.entity_id, e.display_name as name, min(eps.cpu_usage_total) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				}
			} elseif ($db->dbType == "oracle") {
				if ($aggregateFn == "average") {
					$sql = "select * from (
								Select eps.entity_id, e.display_name as name, avg(eps.cpu_usage_total) as value 
									from entity_performance_summary eps
									join entity e on eps.entity_id = e.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."
									and eps.sampletime > sysdate - interval '". $time_frame . "' second
									group by eps.entity_id, e.display_name
									order by value ". $sortOrder . ")
								where rownum <= ". $numElements;
				} elseif ($aggregateFn == "max") {
					$sql = "select * from (
								Select eps.entity_id, e.display_name as name, max(eps.cpu_usage_total) as value 
									from entity_performance_summary eps
									join entity e on eps.entity_id = e.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."
									and eps.sampletime > sysdate - interval '". $time_frame . "' second
									group by eps.entity_id, e.display_name
									order by value ". $sortOrder . ")
								where rownum <= ". $numElements;
				} elseif ($aggregateFn == "min") {
					$sql = "select * from (
								Select eps.entity_id, e.display_name as name, min(eps.cpu_usage_total) as value 
									from entity_performance_summary eps
									join entity e on eps.entity_id = e.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."
									and eps.sampletime > sysdate - interval '". $time_frame . "' second
									group by eps.entity_id, e.display_name
									order by value ". $sortOrder . ")
								where rownum <= ". $numElements;
				}
			} elseif ($db->dbType == "mssql") {
				if ($aggregateFn == "average") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, avg(eps.cpu_usage_total) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder ;
				} elseif ($aggregateFn == "max") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, max(eps.cpu_usage_total) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder;
				} elseif ($aggregateFn == "min") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, min(eps.cpu_usage_total) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder;
				}
			}
			

		}
		elseif ($metric == "memory") {
			if ($db->dbType == "mysql") {						
				if ($aggregateFn == "average") {
					$sql = "Select eps.entity_id, e.display_name as name, avg(eps.memory_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				} elseif ($aggregateFn == "max") {
					$sql = "Select eps.entity_id, e.display_name as name, max(eps.memory_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				} elseif ($aggregateFn == "min") {
					$sql = "Select eps.entity_id, e.display_name as name, min(eps.memory_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				}
			} elseif ($db->dbType == "oracle") {						
				if ($aggregateFn == "average") {
					$sql = "select * from (
								Select eps.entity_id, e.display_name as name, avg(eps.memory_usage) as value 
									from entity_performance_summary eps
									join entity e on eps.entity_id = e.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."
									and eps.sampletime > sysdate - interval '". $time_frame . "' second
									group by eps.entity_id, e.display_name
									order by value ". $sortOrder . ")
								where rownum <= ". $numElements;
				} elseif ($aggregateFn == "max") {
					$sql = "select * from (
								Select eps.entity_id, e.display_name as name, max(eps.memory_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > sysdate - interval '". $time_frame . "' second
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder. ")
							where rownum <= ". $numElements;
				} elseif ($aggregateFn == "min") {
					$sql = "select * from (
								Select eps.entity_id, e.display_name as name, min(eps.memory_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > sysdate - interval '". $time_frame . "' second
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder. ")
							where rownum <= ". $numElements;
				}
			} elseif ($db->dbType == "mssql") {						
				if ($aggregateFn == "average") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, avg(eps.memory_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder;
				} elseif ($aggregateFn == "max") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, max(eps.memory_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder;
				} elseif ($aggregateFn == "min") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, min(eps.memory_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder;
				}
			}
		}
		elseif ($metric == "worst_disk_usage") {
			if ($db->dbType == "mysql") {
				if ($aggregateFn == "average") {
					$sql = "Select eps.entity_id, e.display_name as name, avg(eps.disk_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				} elseif ($aggregateFn == "max") {
					$sql = "Select eps.entity_id, e.display_name as name, max(eps.disk_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				} elseif ($aggregateFn == "min") {
					$sql = "Select eps.entity_id, e.display_name as name, min(eps.disk_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				}
			} elseif ($db->dbType == "oracle") {
				if ($aggregateFn == "average") {
					$sql = "select * from (
								Select eps.entity_id, e.display_name as name, avg(eps.disk_usage) as value 
									from entity_performance_summary eps
									join entity e on eps.entity_id = e.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."
									and eps.sampletime > sysdate - interval '". $time_frame . "' second
									group by eps.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
				} elseif ($aggregateFn == "max") {
					$sql = "select * from (
								Select eps.entity_id, e.display_name as name, max(eps.disk_usage) as value 
									from entity_performance_summary eps
									join entity e on eps.entity_id = e.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."
									and eps.sampletime > sysdate - interval '". $time_frame . "' second
									group by eps.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
				} elseif ($aggregateFn == "min") {
					$sql = "select * from (
								Select eps.entity_id, e.display_name as name, min(eps.disk_usage) as value 
									from entity_performance_summary eps
									join entity e on eps.entity_id = e.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."
									and eps.sampletime > sysdate - interval '". $time_frame . "' second
									group by eps.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
				}
			} elseif ($db->dbType == "mssql") {
				if ($aggregateFn == "average") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, avg(eps.disk_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder;
				} elseif ($aggregateFn == "max") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, max(eps.disk_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder;
				} elseif ($aggregateFn == "min") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, min(eps.disk_usage) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder;
				}
			}
		}
		elseif ($metric == "worst_disk_busy") {
			if ($db->dbType == "mysql") {	
				if ($aggregateFn == "average") {
					$sql = "Select eps.entity_id, e.display_name as name, avg(eps.disk_busy) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				} elseif ($aggregateFn == "max") {
					$sql = "Select eps.entity_id, e.display_name as name, max(eps.disk_busy) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				} elseif ($aggregateFn == "min") {
					$sql = "Select eps.entity_id, e.display_name as name, min(eps.disk_busy) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > date_sub(now(),interval  ". $time_frame . " second)
								group by eps.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				}
			} elseif ($db->dbType == "oracle") {	
				if ($aggregateFn == "average") {
					$sql = "select * from (
								Select eps.entity_id, e.display_name as name, avg(eps.disk_busy) as value 
									from entity_performance_summary eps
									join entity e on eps.entity_id = e.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."
									and eps.sampletime > sysdate - interval '". $time_frame . "' second
									group by eps.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
				} elseif ($aggregateFn == "max") {
					$sql = "select * from (
								Select eps.entity_id, e.display_name as name, max(eps.disk_busy) as value 
									from entity_performance_summary eps
									join entity e on eps.entity_id = e.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."
									and eps.sampletime > sysdate - interval '". $time_frame . "' second
									group by eps.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
				} elseif ($aggregateFn == "min") {
					$sql = "select * from (
									Select eps.entity_id, e.display_name as name, min(eps.disk_busy) as value 
									from entity_performance_summary eps
									join entity e on eps.entity_id = e.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."
									and eps.sampletime > sysdate - interval '". $time_frame . "' second
									group by eps.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
				}
			} elseif ($db->dbType == "mssql") {	
				if ($aggregateFn == "average") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, avg(eps.disk_busy) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder;
				} elseif ($aggregateFn == "max") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, max(eps.disk_busy) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder;
				} elseif ($aggregateFn == "min") {
					$sql = "Select TOP ". $numElements ." eps.entity_id, e.display_name as name, min(eps.disk_busy) as value 
								from entity_performance_summary eps
								join entity e on eps.entity_id = e.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								where eg.entity_group_id in ". $groupIdString ."
								and eps.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
								group by eps.entity_id, e.display_name
								order by value ". $sortOrder;
				}
			}
		}
		elseif ($metric == "vmware_cpu_mhz") {
			if ($db->dbType == "mysql") {
				if ($aggregateFn == "average") {
					$sql = "select e.entity_id, e.display_name as name, avg(vpa.cpu_usage) as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > date_sub(now(),interval  ". $time_frame . "  second)
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				}
				elseif ($aggregateFn == "max") {
					$sql = "select e.entity_id, e.display_name as name, max(vpa.cpu_usage) as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > date_sub(now(),interval  ". $time_frame . "  second)
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				}
				elseif ($aggregateFn == "min") {
					$sql = "select e.entity_id, e.display_name as name, min(vpa.cpu_usage) as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > date_sub(now(),interval  ". $time_frame . "  second)
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				}
			} elseif ($db->dbType == "oracle") {
				if ($aggregateFn == "average") {
					$sql = "select * from (
								select e.entity_id, e.display_name as name, avg(vpa.cpu_usage) as value 
									from vmware_object vo
									join entity e on e.entity_id = vo.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
									join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
									where eg.entity_group_id in ". $groupIdString ."
									and vps.sample_time > sysdate - interval '". $time_frame . "' second
									and vmware_object_type = 'VirtualMachine'
									group by e.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
				}
				elseif ($aggregateFn == "max") {
					$sql = "select * from (
								select e.entity_id, e.display_name as name, max(vpa.cpu_usage) as value 
									from vmware_object vo
									join entity e on e.entity_id = vo.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
									join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
									where eg.entity_group_id in ". $groupIdString ."
									and vps.sample_time > sysdate - interval '". $time_frame . "' second
									and vmware_object_type = 'VirtualMachine'
									group by e.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
				}
				elseif ($aggregateFn == "min") {
					$sql = "select * from (
								select e.entity_id, e.display_name as name, min(vpa.cpu_usage) as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > sysdate - interval '". $time_frame . "' second
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id, e.display_name
								order by value ". $sortOrder. ")
							where rownum <= ". $numElements;
				}
			} elseif ($db->dbType == "mssql") {
				if ($aggregateFn == "average") {
					$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, avg(vpa.cpu_usage) as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > DATEADD(second, -". $time_frame . ", GETDATE())
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id, e.display_name
								order by value ". $sortOrder;
				}
				elseif ($aggregateFn == "max") {
					$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, max(vpa.cpu_usage) as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > DATEADD(second, -". $time_frame . ", GETDATE())
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id, e.display_name
								order by value ". $sortOrder;
				}
				elseif ($aggregateFn == "min") {
					$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, min(vpa.cpu_usage) as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > DATEADD(second, -". $time_frame . ", GETDATE())
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id, e.display_name
								order by value ". $sortOrder;
				}
			}
		}
		elseif ($metric == "vmware_mem_mb") {
			if ($db->dbType == "mysql") {
				if ($aggregateFn == "average") {
					$sql = "select e.entity_id, e.display_name as name, avg(vpa.memory_usage) / 1024 as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > date_sub(now(),interval  ". $time_frame . "  second)
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				}
				elseif ($aggregateFn == "max") {
					$sql = "select e.entity_id, e.display_name as name, max(vpa.memory_usage) / 1024 as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > date_sub(now(),interval  ". $time_frame . "  second)
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				}
				elseif ($aggregateFn == "min") {
					$sql = "select e.entity_id, e.display_name as name, min(vpa.memory_usage) / 1024 as value
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > date_sub(now(),interval  ". $time_frame . "  second)
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id
								order by value ". $sortOrder .
								" limit ". $numElements;
				}
			} elseif ($db->dbType == "oracle") {
				if ($aggregateFn == "average") {
					$sql = "select * from (
								select e.entity_id, e.display_name as name, avg(vpa.memory_usage) / 1024 as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > sysdate - interval '". $time_frame . "' second
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id, e.display_name
								order by value ". $sortOrder. ")
							where rownum <= ". $numElements;
				}
				elseif ($aggregateFn == "max") {
					$sql = "select * from (
								select e.entity_id, e.display_name as name, max(vpa.memory_usage) / 1024 as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > sysdate - interval '". $time_frame . "' second
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id, e.display_name
								order by value ". $sortOrder. ")
							where rownum <= ". $numElements;
				}
				elseif ($aggregateFn == "min") {
					$sql = "select * from (
								select e.entity_id, e.display_name as name, min(vpa.memory_usage) / 1024 as value
									from vmware_object vo
									join entity e on e.entity_id = vo.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
									join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
									where eg.entity_group_id in ". $groupIdString ."
									and vps.sample_time > sysdate - interval '". $time_frame . "' second
									and vmware_object_type = 'VirtualMachine'
									group by e.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
				}
			} elseif ($db->dbType == "mssql") {
				if ($aggregateFn == "average") {
					$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, avg(vpa.memory_usage) / 1024 as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > DATEADD(second, -". $time_frame . ", GETDATE())
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id, e.display_name
								order by value ". $sortOrder;
				}
				elseif ($aggregateFn == "max") {
					$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, max(vpa.memory_usage) / 1024 as value 
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > DATEADD(second, -". $time_frame . ", GETDATE())
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id, e.display_name
								order by value ". $sortOrder;
				}
				elseif ($aggregateFn == "min") {
					$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, min(vpa.memory_usage) / 1024 as value
								from vmware_object vo
								join entity e on e.entity_id = vo.entity_id
								join entity_group eg on eg.entity_group_id = e.entity_group_id
								join vmware_perf_sample vps on vps.vmware_object_id = vo.vmware_object_id
								join vmware_perf_aggregate vpa on vpa.sample_id = vps.sample_id
								where eg.entity_group_id in ". $groupIdString ."
								and vps.sample_time > DATEADD(second, -". $time_frame . ", GETDATE())
								and vmware_object_type = 'VirtualMachine'
								group by e.entity_id, e.display_name
								order by value ". $sortOrder;
				}
			}
		}
		
		
		
		// If the metric is numeric, then this is a service monitor metrics
		elseif (is_numeric($metric)) {
			// Get Data Type
			$sql = "select ep.data_type_id as datatype
					from erdc_parameter ep
					where ep.erdc_parameter_id = ". $metric;
					
			$result = $db->execQuery($sql);
			$datatype = $result[0][DATATYPE];
			
			// Int - look at erdc_int_data
			if ($datatype == 2) {
				if ($db->dbType == "mysql") {
					if ($aggregateFn == "average") {
						$sql = "select e.entity_id, e.display_name as name, avg(eid.value) as value 
									from erdc_int_data eid
									join erdc_instance ei on ei.erdc_instance_id = eid.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and eid.erdc_parameter_id = ". $metric . "  
									and eid.sampletime > date_sub(now(),interval  ". $time_frame . " second)
									group by e.entity_id
									order by value ". $sortOrder .
									" limit ". $numElements;
					} elseif ($aggregateFn == "max") {
						$sql = "select e.entity_id, e.display_name as name, max(eid.value) as value 
									from erdc_int_data eid
									join erdc_instance ei on ei.erdc_instance_id = eid.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and eid.erdc_parameter_id = ". $metric . "  
									and eid.sampletime > date_sub(now(),interval  ". $time_frame . " second)
									group by e.entity_id
									order by value ". $sortOrder .
									" limit ". $numElements;
					} elseif ($aggregateFn == "min") {
						$sql = "select e.entity_id, e.display_name as name, min(eid.value) as value 
									from erdc_int_data eid
									join erdc_instance ei on ei.erdc_instance_id = eid.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and eid.erdc_parameter_id = ". $metric . "  
									and eid.sampletime > date_sub(now(),interval  ". $time_frame . " second)
									group by e.entity_id
									order by value ". $sortOrder .
									" limit ". $numElements;
					}
				} elseif ($db->dbType == "oracle") {
					if ($aggregateFn == "average") {
						$sql = "select * from (
									select e.entity_id, e.display_name as name, avg(eid.value) as value 
										from erdc_int_data eid
										join erdc_instance ei on ei.erdc_instance_id = eid.erdc_instance_id
										join entity e on e.entity_id = ei.entity_id
										join entity_group eg on eg.entity_group_id = e.entity_group_id
										where eg.entity_group_id in ". $groupIdString ."  
										and eid.erdc_parameter_id = ". $metric . "  
										and eid.sampletime > sysdate - interval '". $time_frame . "' second
										group by e.entity_id, e.display_name
										order by value ". $sortOrder. ")
									where rownum <= ". $numElements;
					} elseif ($aggregateFn == "max") {
						$sql = "select * from (
									select e.entity_id, e.display_name as name, max(eid.value) as value 
										from erdc_int_data eid
										join erdc_instance ei on ei.erdc_instance_id = eid.erdc_instance_id
										join entity e on e.entity_id = ei.entity_id
										join entity_group eg on eg.entity_group_id = e.entity_group_id
										where eg.entity_group_id in ". $groupIdString ."  
										and eid.erdc_parameter_id = ". $metric . "  
										and eid.sampletime > sysdate - interval '". $time_frame . "' second
										group by e.entity_id, e.display_name
										order by value ". $sortOrder. ")
									where rownum <= ". $numElements;
					} elseif ($aggregateFn == "min") {
						$sql = "select * from (
									select e.entity_id, e.display_name as name, min(eid.value) as value 
									from erdc_int_data eid
									join erdc_instance ei on ei.erdc_instance_id = eid.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and eid.erdc_parameter_id = ". $metric . "  
									and eid.sampletime > sysdate - interval '". $time_frame . "' second
									group by e.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
					}
				} elseif ($db->dbType == "mssql") {
					if ($aggregateFn == "average") {
						$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, avg(eid.value) as value 
									from erdc_int_data eid
									join erdc_instance ei on ei.erdc_instance_id = eid.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and eid.erdc_parameter_id = ". $metric . "  
									and eid.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
									group by e.entity_id, e.display_name
									order by value ". $sortOrder;
					} elseif ($aggregateFn == "max") {
						$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, max(eid.value) as value 
									from erdc_int_data eid
									join erdc_instance ei on ei.erdc_instance_id = eid.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and eid.erdc_parameter_id = ". $metric . "  
									and eid.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
									group by e.entity_id, e.display_name
									order by value ". $sortOrder;
					} elseif ($aggregateFn == "min") {
						$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, min(eid.value) as value 
									from erdc_int_data eid
									join erdc_instance ei on ei.erdc_instance_id = eid.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and eid.erdc_parameter_id = ". $metric . "  
									and eid.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
									group by e.entity_id, e.display_name
									order by value ". $sortOrder;
					}
				}
			}
			
			// Decimal - look at erdc_decimal_data
			if ($datatype == 3) {
				if ($db->dbType == "mysql") {
					if ($aggregateFn == "average") {
						$sql = "select e.entity_id, e.display_name as name, avg(edd.value) as value 
									from erdc_decimal_data edd
									join erdc_instance ei on ei.erdc_instance_id = edd.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and edd.erdc_parameter_id = ". $metric . "  
									and edd.sampletime > date_sub(now(),interval  ". $time_frame . " second)
									group by e.entity_id
									order by value ". $sortOrder .
									" limit ". $numElements;
					} elseif ($aggregateFn == "max") {
						$sql = "select e.entity_id, e.display_name as name, max(edd.value) as value 
									from erdc_decimal_data edd
									join erdc_instance ei on ei.erdc_instance_id = edd.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and edd.erdc_parameter_id = ". $metric . "  
									and edd.sampletime > date_sub(now(),interval  ". $time_frame . " second)
									group by e.entity_id
									order by value ". $sortOrder .
									" limit ". $numElements;

					} elseif ($aggregateFn == "min") {
						$sql = "select e.entity_id, e.display_name as name, min(edd.value) as value 
									from erdc_decimal_data edd
									join erdc_instance ei on ei.erdc_instance_id = edd.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and edd.erdc_parameter_id = ". $metric . "  
									and edd.sampletime > date_sub(now(),interval  ". $time_frame . " second)
									group by e.entity_id
									order by value ". $sortOrder .
									" limit ". $numElements;
					}
				} elseif ($db->dbType == "oracle") {
					if ($aggregateFn == "average") {
						$sql = "select * from (
									select e.entity_id, e.display_name as name, avg(edd.value) as value 
									from erdc_decimal_data edd
									join erdc_instance ei on ei.erdc_instance_id = edd.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and edd.erdc_parameter_id = ". $metric . "  
									and edd.sampletime > sysdate - interval '". $time_frame . "' second
									group by e.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
					} elseif ($aggregateFn == "max") {
						$sql = "select * from (
									select e.entity_id, e.display_name as name, max(edd.value) as value 
									from erdc_decimal_data edd
									join erdc_instance ei on ei.erdc_instance_id = edd.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and edd.erdc_parameter_id = ". $metric . "  
									and edd.sampletime > sysdate - interval '". $time_frame . "' second
									group by e.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;

					} elseif ($aggregateFn == "min") {
						$sql = "select * from (
									select e.entity_id, e.display_name as name, min(edd.value) as value 
									from erdc_decimal_data edd
									join erdc_instance ei on ei.erdc_instance_id = edd.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and edd.erdc_parameter_id = ". $metric . "  
									and edd.sampletime > sysdate - interval '". $time_frame . "' second
									group by e.entity_id, e.display_name
									order by value ". $sortOrder. ")
								where rownum <= ". $numElements;
					}
				} elseif ($db->dbType == "mssql") {
					if ($aggregateFn == "average") {
						$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, avg(edd.value) as value 
									from erdc_decimal_data edd
									join erdc_instance ei on ei.erdc_instance_id = edd.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and edd.erdc_parameter_id = ". $metric . "  
									and edd.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
									group by e.entity_id, e.display_name
									order by value ". $sortOrder;
					} elseif ($aggregateFn == "max") {
						$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, max(edd.value) as value 
									from erdc_decimal_data edd
									join erdc_instance ei on ei.erdc_instance_id = edd.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and edd.erdc_parameter_id = ". $metric . "  
									and edd.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
									group by e.entity_id, e.display_name
									order by value ". $sortOrder;

					} elseif ($aggregateFn == "min") {
						$sql = "select TOP ". $numElements ." e.entity_id, e.display_name as name, min(edd.value) as value 
									from erdc_decimal_data edd
									join erdc_instance ei on ei.erdc_instance_id = edd.erdc_instance_id
									join entity e on e.entity_id = ei.entity_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and edd.erdc_parameter_id = ". $metric . "  
									and edd.sampletime > DATEADD(second, -". $time_frame . ", GETDATE())
									group by e.entity_id, e.display_name
									order by value ". $sortOrder;
					}
				}
			}
			
			// Ranged - look at ranged_object_value & ranged_object
			if ($datatype == 6) 
			{	
				if ($db->dbType == "mysql") {
					if ($aggregateFn == "average") {
						$sql =	"select e.entity_id, concat(e.display_name as name, '(',ro.object_name, ')') as name, avg(value) as value 
									from ranged_object_value rov
									join ranged_object ro on rov.ranged_object_id = ro.id
									join erdc_instance ei on ei.erdc_instance_id = ro.instance_id				
									join erdc_configuration ec on ei.configuration_id = ec.id
									join entity e on e.entity_id = ei.entity_id
									join erdc_parameter ep on ep.erdc_base_id = ec.erdc_base_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and ep.name = rov.name
									and ep.erdc_parameter_id = ". $metric . "  
									and rov.sample_time > date_sub(now(),interval  ". $time_frame . " second)
									group by ro.id
									order by value ". $sortOrder .
									" limit ". $numElements;
					} elseif ($aggregateFn == "max") {
						$sql =	"select e.entity_id, concat(e.display_name as name, '(',ro.object_name, ')') as name, max(value) as value 
									from ranged_object_value rov
									join ranged_object ro on rov.ranged_object_id = ro.id
									join erdc_instance ei on ei.erdc_instance_id = ro.instance_id				
									join erdc_configuration ec on ei.configuration_id = ec.id
									join entity e on e.entity_id = ei.entity_id
									join erdc_parameter ep on ep.erdc_base_id = ec.erdc_base_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and ep.name = rov.name
									and ep.erdc_parameter_id = ". $metric . "  
									and rov.sample_time > date_sub(now(),interval  ". $time_frame . " second)
									group by ro.id
									order by value ". $sortOrder .
									" limit ". $numElements;
					} elseif ($aggregateFn == "min") {
						$sql =	"select e.entity_id, concat(e.display_name as name, '(',ro.object_name, ')') as name, min(value) as value 
									from ranged_object_value rov
									join ranged_object ro on rov.ranged_object_id = ro.id
									join erdc_instance ei on ei.erdc_instance_id = ro.instance_id				
									join erdc_configuration ec on ei.configuration_id = ec.id
									join entity e on e.entity_id = ei.entity_id
									join erdc_parameter ep on ep.erdc_base_id = ec.erdc_base_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and ep.name = rov.name
									and ep.erdc_parameter_id = ". $metric . "  
									and rov.sample_time > date_sub(now(),interval  ". $time_frame . " second)
									group by ro.id
									order by value ". $sortOrder .
									" limit ". $numElements;
					}
				} elseif ($db->dbType == "oracle") {
					if ($aggregateFn == "average") {
						$sql =	"select entity_id, concat(name, concat('(', concat(object_name, ')'))) as name, value from 
									(select e.entity_id, e.display_name as name, ro.object_name, avg(rov.value) as value
										from ranged_object_value rov
										join ranged_object ro on rov.ranged_object_id = ro.id
										join erdc_instance ei on ei.erdc_instance_id = ro.instance_id				
										join erdc_configuration ec on ei.configuration_id = ec.id
										join entity e on e.entity_id = ei.entity_id
										join erdc_parameter ep on ep.erdc_base_id = ec.erdc_base_id
										join entity_group eg on eg.entity_group_id = e.entity_group_id
										where eg.entity_group_id in ". $groupIdString ."  
										and ep.name = rov.name
										and ep.erdc_parameter_id = ". $metric . "  
										and rov.sample_time > sysdate - interval '". $time_frame . "' second									
										group by e.entity_id, e.display_name, ro.object_name
										order by value ". $sortOrder . ")" .
										" where rownum <= ". $numElements;								
					} elseif ($aggregateFn == "max") {
						$sql =	"select entity_id, concat(name, concat('(', concat(object_name, ')'))) as name, value from 
									(select e.entity_id, e.display_name as name, ro.object_name, max(rov.value) as value
										from ranged_object_value rov
										join ranged_object ro on rov.ranged_object_id = ro.id
										join erdc_instance ei on ei.erdc_instance_id = ro.instance_id				
										join erdc_configuration ec on ei.configuration_id = ec.id
										join entity e on e.entity_id = ei.entity_id
										join erdc_parameter ep on ep.erdc_base_id = ec.erdc_base_id
										join entity_group eg on eg.entity_group_id = e.entity_group_id
										where eg.entity_group_id in ". $groupIdString ."  
										and ep.name = rov.name
										and ep.erdc_parameter_id = ". $metric . "  
										and rov.sample_time > sysdate - interval '". $time_frame . "' second									
										group by e.entity_id, e.display_name, ro.object_name
										order by value ". $sortOrder . ")" .
										" where rownum <= ". $numElements;
					} elseif ($aggregateFn == "min") {
						$sql =	"select entity_id, concat(name, concat('(', concat(object_name, ')'))) as name, value from 
									(select e.entity_id, e.display_name as name, ro.object_name, min(rov.value) as value
										from ranged_object_value rov
										join ranged_object ro on rov.ranged_object_id = ro.id
										join erdc_instance ei on ei.erdc_instance_id = ro.instance_id				
										join erdc_configuration ec on ei.configuration_id = ec.id
										join entity e on e.entity_id = ei.entity_id
										join erdc_parameter ep on ep.erdc_base_id = ec.erdc_base_id
										join entity_group eg on eg.entity_group_id = e.entity_group_id
										where eg.entity_group_id in ". $groupIdString ."  
										and ep.name = rov.name
										and ep.erdc_parameter_id = ". $metric . "  
										and rov.sample_time > sysdate - interval '". $time_frame . "' second									
										group by e.entity_id, e.display_name, ro.object_name
										order by value ". $sortOrder . ")" .
										" where rownum <= ". $numElements;
					}
				} elseif ($db->dbType == "mssql") {
					if ($aggregateFn == "average") {
						$sql =	"select TOP ". $numElements ."  e.entity_id, e.display_name + ' (' + ro.object_name + ')' as name, avg(value) as value 
									from ranged_object_value rov
									join ranged_object ro on rov.ranged_object_id = ro.id
									join erdc_instance ei on ei.erdc_instance_id = ro.instance_id				
									join erdc_configuration ec on ei.configuration_id = ec.id
									join entity e on e.entity_id = ei.entity_id
									join erdc_parameter ep on ep.erdc_base_id = ec.erdc_base_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and ep.name = rov.name
									and ep.erdc_parameter_id = ". $metric . "  
									and rov.sample_time > DATEADD(second, -". $time_frame . ", GETDATE())
									group by ro.id, e.entity_id, e.display_name, ro.object_name
									order by value ". $sortOrder;
					} elseif ($aggregateFn == "max") {
						$sql =	"select TOP ". $numElements ."  e.entity_id, e.display_name + ' (' + ro.object_name + ')' as name, max(value) as value 
									from ranged_object_value rov
									join ranged_object ro on rov.ranged_object_id = ro.id
									join erdc_instance ei on ei.erdc_instance_id = ro.instance_id				
									join erdc_configuration ec on ei.configuration_id = ec.id
									join entity e on e.entity_id = ei.entity_id
									join erdc_parameter ep on ep.erdc_base_id = ec.erdc_base_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and ep.name = rov.name
									and ep.erdc_parameter_id = ". $metric . "  
									and rov.sample_time > DATEADD(second, -". $time_frame . ", GETDATE())
									group by ro.id, e.entity_id, e.display_name, ro.object_name
									order by value ". $sortOrder;
					} elseif ($aggregateFn == "min") {
						$sql =	"select TOP ". $numElements ."  e.entity_id, e.display_name + ' (' + ro.object_name + ')' as name, min(value) as value 
									from ranged_object_value rov
									join ranged_object ro on rov.ranged_object_id = ro.id
									join erdc_instance ei on ei.erdc_instance_id = ro.instance_id				
									join erdc_configuration ec on ei.configuration_id = ec.id
									join entity e on e.entity_id = ei.entity_id
									join erdc_parameter ep on ep.erdc_base_id = ec.erdc_base_id
									join entity_group eg on eg.entity_group_id = e.entity_group_id
									where eg.entity_group_id in ". $groupIdString ."  
									and ep.name = rov.name
									and ep.erdc_parameter_id = ". $metric . "  
									and rov.sample_time > DATEADD(second, -". $time_frame . ", GETDATE())
									group by ro.id, e.entity_id, e.display_name, ro.object_name
									order by value ". $sortOrder;
					}
				}
			}
		}

		$result = $db->execQuery($sql);
		foreach($result as $row) {
			array_push($returnArray, array("id"=>$row[ENTITY_ID], "name"=>$row[NAME], "value"=>floatval($row[VALUE])));
		}

		echo json_encode($returnArray);
	}
	elseif ($query_type == "test") {
		//$testArray = array();
		$testArray = getChildGroups($db, 1);
		print_r($testArray);

	}
	
}

// Returns all child groups, including the parent group ID
// returns a string containing all the group ID's
// e.g. (1,3,5)
// To be used with select * from entity_group where entity_group_id in (1,3,5)
function getChildGroups($db, $parentGroupID) {
	$needToFind = array();
	$foundGroup = array();
	$foundGroupString = "(";
	array_push($needToFind, $parentGroupID);

	$returnArray = array();	
	do {
		$currentGroupID = array_pop($needToFind);
		$sql = "select entity_group_id from entity_group 
				where parent_id = ". $currentGroupID;		
		array_push($foundGroup, $currentGroupID);
		$foundGroupString = $foundGroupString. $currentGroupID . ",";
		
		$result = $db->execQuery($sql);
		
		if (!empty($result)) {
			foreach($result as $row) {
				array_push($needToFind, $row[ENTITY_GROUP_ID]);
				
			}
		}
	}	while (!empty($needToFind));
	$foundGroupString = substr_replace($foundGroupString ,"",-1);
	$foundGroupString = $foundGroupString. ")" ;
	return $foundGroupString;

}






?>