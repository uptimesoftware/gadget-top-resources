	// Initialize variables
	var date = new Date();
	var uptimeOffset = date.getTimezoneOffset()*60;
	var debugMode = null;
	var interval = null;
	var requestString = null;
	var myChart = null;
	var myChartDimensions = null;
	var renderSuccessful = null;  //Add this later
	var divsToDim = ['#widgetChart', '#widgetSettings'];
	var settings = {metricType: null, metricValue: null, elementValue: null,
			refreshInterval: null, chartType: null,
			chartTitle: null, seriesTitle: null};
	var gadgetInstanceId = uptimeGadget.getInstanceId();	
	var gadgetGetMetricsPath = '/gadgets/instances/' + gadgetInstanceId + '/getmetrics.php';
	var normalGetMetricsPath = 'getmetrics.php';
	//var relativeGetMetricsPath = '/gadgets/metricchart/getmetrics.php';
	//var getMetricsPath = '/gadgets/topresource/topresource.php';
	var currentURL = $("script#ownScript").attr("src");
	var getMetricsPath = currentURL.substr(0,$("script#ownScript").attr("src").lastIndexOf("/")+1) + 'topresource.php';
	
	var timeFrameSliderOptions = {"1" : "3600", "2" : "21600",  "3" : "43200",
					"4" : "86400", "5" : "172800", "6" : "604800",
					"7" : "1209600", "8" : "2592000"}
	var refreshIntervalOptions = {"10000" : "10 seconds", "30000" : "30 seconds", 
					"60000" : "minute", "300000" : "5 minutes",
					"600000" : "10 minutes"}
	var refreshIntervalSliderOptions = {"1" : "10000", "2" : "30000", "3" : "60000",
						"4" : "300000", "5" : "600000" }
	
	if (debugMode) {
		console.log('Metric Chart: Debug logging enabled');
	}
	
	if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Current path to getmetrics.php: '
				    + getMetricsPath)};
	
	// Initialize handlers
	uptimeGadget.registerOnEditHandler(showEditPanel);
	uptimeGadget.registerOnLoadHandler(function(onLoadData) {
		if (onLoadData.hasPreloadedSettings()) {
			goodLoad(onLoadData.settings);
		} else {
			uptimeGadget.loadSettings().then(goodLoad, onBadAjax);
		}
	});
	uptimeGadget.registerOnResizeHandler(resizeGadget);

	
	// Populate dropdowns
	populateDropdowns();
	
	
	// Unleash the popovers!
	var popOptions = {
		delay: {show: 1000}
		}
	// Enable multiselect
	var popTargets = [$("#metric-type-radio"), $("#service-monitor-metrics-div"),
			  $("#group-name-div"), $("#performance-metrics-div"),
			  $("#performance-elements-div"), $("#timeFrameSliderAndLabel"),
			  $("#refreshIntervalSliderAndLabel"), $("#chart-type-radio"),
			  $("#chart-title-btn")]
	$.each (popTargets, function(index, target) {
			target.popover(popOptions)
			});
	
	// Clear alerts and save settings on configuration closure
	$("#closeSettings").click(function() {
		// Validate whether one of the metric radio buttons is selected
		if (($("#service-monitor-metrics-radio").hasClass('active')) ||
			($("#performance-metrics-radio").hasClass('active')) ||
			($("#vmware-metrics-radio").hasClass('active'))) {
				saveSettings();
		} else {
			displayError('Please select the metric type and metric', '');
		}
		});
	$("#closeNoSave").click(function() {
		$("#widgetSettings").slideUp();
		});
	// Open config panel on double-click
	$("#widgetChart").dblclick(function() {
		showEditPanel();
		});	
	// Toggle debug logging on double-click of 'eye' icon
	$("#visualOptionsIcon").dblclick(function() {
		if (debugMode == null) {
			debugMode = true;
			console.log('Gadget #' + gadgetInstanceId + ' - Debug logging enabled');
		} else if (debugMode == true) {
			debugMode = false;
			console.log('Gadget #' + gadgetInstanceId + ' - Debug logging disabled');
		}
	});
	
	// Metric type changed
	$("#metric-type-radio").on('change', function(evt, params) {
		$('div.container').css('overflowY', 'auto');
		$("div.container").css('overflowX', 'hidden');
		$("div.container").css('height',$(window).height());


		
		if ($('#service-monitor-metrics-btn').is(':checked')) {
			if (settings.metricType !== 'servicemonitor'){
				//$("#options-div").hide();
			}
			$("#performance-div").hide();
			$("#vmware-div").hide();
			$("#service-monitor-div").show();
			$("select.service-monitor-metrics").chosen();
			$("select.service-monitor-elements").chosen();
			$("select.service-monitor-ranged").chosen();
			
			requestString = getMetricsPath + '?call=listMetrics';
			if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Requesting: ' + requestString)};
			$.getJSON(requestString, function(data) {
			}).done(function(data) {
				if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Request succeeded!')};
				$("select.service-monitor-metrics").empty();
				$.each(data, function(key, val) {
					$("select.service-monitor-metrics").append('<option value="' + val.ERDC_PARAM + '">' + val.NAME + ' - ' + val.SHORT_DESC + '</option>');
				});
				$("select.service-monitor-metrics").trigger("chosen:updated");
				if (typeof metricValue !== 'undefined' && metricType == 'servicemonitor') {
					if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting service monitor metric droptown to: '
								    + metricValue)};	   	
					$("select.service-monitor-metrics").val(metricValue).trigger("chosen:updated").trigger('change');		
				}
				$("#service-monitor-metric-count").text($('#service-monitor-metrics option').length);
			}).fail(function(jqXHR, textStatus, errorThrown) {
				console.log('Gadget #' + gadgetInstanceId + ' - Request failed! ' + textStatus);
			}).always(function() {
				// console.log('Request completed.');
			});
		}
		else if ($('#performance-metrics-btn').is(':checked')) {
			if (settings.metricType !== 'performance'){
				//$("#options-div").hide();
			}
			$("#service-monitor-div").hide();
			$("#vmware-div").hide();
			$("#performance-div").show();
			$("select.performance-metrics").chosen();
			$("select.performance-elements").chosen();
			
			$("select.performance-metrics").empty();
			$("select.performance-metrics").append('<option value="cpu">CPU Used (%)</option>');
			$("select.performance-metrics").append('<option value="memory">Memory Used (%)</option>');
			//$("select.performance-metrics").append('<option value="used_swap_percent">Swap - Used (%)</option>');
			$("select.performance-metrics").append('<option value="worst_disk_usage">Disk - Worst Disk Used (%)</option>');
			$("select.performance-metrics").append('<option value="worst_disk_busy">Disk - Worst Disk Busy (%)</option>');
			
			if (typeof metricValue !== 'undefined' && metricType == 'performance') {
				if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting performance monitor metric droptown to: '
							    + metricValue)};
				$("select.performance-metrics").val(metricValue).trigger("chosen:updated").trigger('change');			
			} else {
				$("select.performance-metrics").trigger("chosen:updated").trigger('change');
			}
		}
		

		else if ($('#vmware-metrics-btn').is(':checked')) {
			if (settings.metricType !== 'vmware'){
				//$("#options-div").hide();
			}
			$("#service-monitor-div").hide();
			$("#performance-div").hide();
			$("#vmware-div").show();
			$("select.vmware-metrics").chosen();

			$("select.vmware-metrics").empty();
			//$("select.vmware-metrics").append('<option value="vmware_cpu_pct">CPU (%)</option>');
			$("select.vmware-metrics").append('<option value="vmware_cpu_mhz">CPU (MHz)</option>');
			//$("select.vmware-metrics").append('<option value="vmware_mem_pct">Memory (%)</option>');
			$("select.vmware-metrics").append('<option value="vmware_mem_mb">Memory (MB)</option>');
			
			if (typeof metricValue !== 'undefined' && metricType == 'vmware') {
				if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting vmware metric droptown to: '
							    + metricValue)};
				$("select.vmware-metrics").val(metricValue).trigger("chosen:updated").trigger('change');			
			} else {
				$("select.vmware-metrics").trigger("chosen:updated").trigger('change');
			}
		}
		
	});
	
	// Service monitor metric changed
	$("select.service-monitor-metrics").on('change', function(evt, params) {
		$("select.service-monitor-elements").empty();
		$("select.service-monitor-elements").trigger("chosen:updated");
		requestString = getMetricsPath + '?uptime_offest=' + uptimeOffset + '&query_type=elements_for_monitor&monitor='
						+ $("select.service-monitor-metrics").val();
		if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Requesting: ' + requestString)};
		$.getJSON(requestString, function(data) {
		}).done(function(data) {
			if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Request succeeded!')};
			$("select.service-monitor-elements").empty();
			$.each(data, function(key, val) {
				$("select.service-monitor-elements").append('<option value="' + key + '">' + val + '</option>');
			});
			if (typeof elementValue !== 'undefined' && metricType == 'servicemonitor') {
				if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting service monitor element droptown to: '
							    + elementValue)};
				$("select.service-monitor-elements").val(elementValue).trigger("chosen:updated").trigger('change');
			} else {
				$("select.service-monitor-elements").trigger("chosen:updated").trigger('change');
			}
			$("#service-monitor-element-count").text($('#service-monitor-elements option').size());
		}).fail(function(jqXHR, textStatus, errorThrown) {
			console.log('Gadget #' + gadgetInstanceId + ' - Request failed! ' + textStatus);
		}).always(function() {
			// console.log('Request completed.');
		});
	});	

	// Service monitor element changed
	$("select.service-monitor-elements").on('change', function(evt, params) {
		launchDivs();
	});
	
	// Ranged metric changed
	$("select.service-monitor-elements").on('change', function(evt, params) {
		
		if ($("select.service-monitor-metrics").val().slice(-1) == "6") {
			if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Ranged Data Selected')};
			$("#service-monitor-ranged-div").show();
		} else {
			$("#service-monitor-ranged-div").hide();
		}		
		
		$("select.service-monitor-ranged").empty();
		$("select.service-monitor-ranged").trigger("chosen:updated");
		requestString = getMetricsPath + '?uptime_offest=' + uptimeOffset + '&query_type=ranged_objects&element='
						+ $("select.service-monitor-elements").val() 
						+ '&object_list=' + $("select.service-monitor-ranged").val();
		if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Requesting: ' + requestString)};
		$.getJSON(requestString, function(data) {
		}).done(function(data) {
			if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Request succeeded!')};
			$("select.service-monitor-ranged").empty();
			$.each(data, function(key, val) {
				$("select.service-monitor-ranged").append('<option value="' + key + '">' + val + '</option>');
			});
			if (typeof objectValue !== 'undefined' && metricType == 'servicemonitor') {
				if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting service monitor ranged object droptown to: '
							    + objectValue)};
				$("select.service-monitor-ranged").val(objectValue).trigger("chosen:updated").trigger('change');
			} else {
				$("select.service-monitor-ranged").trigger("chosen:updated").trigger('change');
			}
			$("#service-monitor-element-count").text($('#service-monitor-ranged option').size());
		}).fail(function(jqXHR, textStatus, errorThrown) {
			console.log('Gadget #' + gadgetInstanceId + ' - Request failed! ' + textStatus);
		}).always(function() {
			// console.log('Request completed.');
		});
	});
	
	
	
	
	// Performance metric changed
	/*
	$("select.performance-metrics").on('change', function(evt, params) {
		$("select.performance-elements").empty();
		$("select.performance-elements").trigger("chosen:updated");
		requestString = getMetricsPath + '?uptime_offest=' + uptimeOffset + '&query_type=elements_for_performance';
		if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Requesting: ' + requestString)};
		$.getJSON(requestString, function(data) {}).done(function(data) {
			if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Request succeeded!')};
			$("select.performance-elements").empty();
			$.each(data, function(key, val) {
				$("select.performance-elements").append('<option value="' + val + '">' + key + '</option>');	
			});
			if (typeof elementValue !== 'undefined' && metricType == 'performance') {
				if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting performance monitor element droptown to: '
							    + elementValue)};
				$("select.performance-elements").val(elementValue).trigger("chosen:updated").trigger('change');
			} else {
				$("select.performance-elements").trigger("chosen:updated").trigger('change');
			}
			$("#performance-element-count").text($('#performance-elements option').size());
		}).fail(function(jqXHR, textStatus, errorThrown) {
			console.log('Gadget #' + gadgetInstanceId + ' - Request failed! ' + textStatus);
		}).always(function() {
			// console.log('Request completed.');
		});	
	});
	*/
	
	// Performance monitor element changed
	$("select.performance-elements").on('change', function(evt, params) {
		launchDivs();
	});

	
	launchDivs();
	
	getAllGroups().then(function(allGroups) {
		$("select#group-name").append('<optgroup id="select-group" label="Group:"></optgroup>');
		
		$.each(allGroups, function(i, group) {		
			//$("select#group-name").append($("<option />").val(group.ENTITY_GROUP_ID).text(group.NAME));
			$("optgroup#select-group").append($("<option />").val(group.ENTITY_GROUP_ID).text(group.NAME));
		});
			
	})
	.then(function() {
		// Chosen-ify dropdowns
		$("select.group-name").chosen();
		$("select.topOrBottom").chosen();
		$("select.number-elements").chosen();
		$("select.aggregate-fn").chosen();
		$("select.time-period").chosen();
	});
	
	
	
	function getAllGroups() {
		var deferred = UPTIME.pub.gadgets.promises.defer();
		$.ajax(getMetricsPath+"?call=getAllGroup", {
			cache : false
		}).done(function(data, textStatus, jqXHR) {
			deferred.resolve(data);
		}).fail(function(jqXHR, textStatus, errorThrown) {
			deferred.reject(UPTIME.pub.errors.toDisplayableJQueryAjaxError(jqXHR, textStatus, errorThrown, this));
		});
		return deferred.promise;
	}
	
	$("select.network-metrics").append('<option value="kbps_total_rate">Total Rate (Mbps)</option>');
	
	
	// Key functions
	function populateDropdowns() {

		$("#refreshIntervalSlider").slider({
			range: "max", value: 4, min: 1, max: 5, animate: true,
			slide: function( event, ui ) {
				$("#refreshIntervalLabelContents").text('Every '
							+ refreshIntervalOptions[refreshIntervalSliderOptions[ui.value]]);
				}
			});
		//$("#options-div").hide();
	}
	
	function showEditPanel() {
		if (myChart) {
			myChart.stopTimer();
		}
		$("#widgetBody").slideDown(function() {
			$("#widgetSettings").slideDown();
		});
		$("#widgetChart").height($(window).height());
	}
	
	function saveSettings() {
		if ($("#service-monitor-metrics-radio").hasClass('active')) {
			settings.metricType = 'servicemonitor';
			settings.metricValue = $("select.service-monitor-metrics").val();
			settings.elementValue = $("select.service-monitor-elements").val();
			if ($("select.service-monitor-metrics").val().slice(-1) == "6") {
				settings.objectValue = $("select.service-monitor-ranged").val();
			}
			if ($("#chart-title-btn").hasClass('active')) {
				settings.chartTitle = $('select.service-monitor-metrics option:selected').text();
			} else {
				settings.chartTitle = "";
			}
			settings.seriesTitle = $('select.service-monitor-metrics option:selected').text();
		}
		else if ($("#performance-metrics-radio").hasClass('active')) {
			settings.metricType = 'performance';
			settings.metricValue = $("select.performance-metrics").val();
			settings.elementValue = $("select.performance-elements").val();
			if ($("#chart-title-btn").hasClass('active')) {
				settings.chartTitle = $('select.performance-metrics option:selected').text();
			} else {
				settings.chartTitle = "";
			}
			settings.seriesTitle = $('select.performance-metrics option:selected').text();
		}
		else if ($("#vmware-metrics-radio").hasClass('active')) {
			settings.metricType = 'vmware';
			settings.metricValue = $("select.vmware-metrics").val();
			if ($("#chart-title-btn").hasClass('active')) {
				settings.chartTitle = $('select.vmware-metrics option:selected').text();
							
			} else {
				settings.chartTitle = "";
			}
			settings.seriesTitle = $('select.vmware-metrics option:selected').text();
		}

		refreshIntervalIndex = $("#refreshIntervalSlider").slider("value");
		settings.refreshInterval = refreshIntervalSliderOptions[refreshIntervalIndex];
		
		var checkedButton = $("input[name='graph-type-options']:checked").val();
		if (checkedButton == 'areaspline'){
			settings.chartType = checkedButton;
		}
		else if (checkedButton == 'spline'){
			settings.chartType = checkedButton;
		}
		else {
			settings.chartType = 'spline';
		}
		
		settings.groupId = $("select#group-name").val();
		settings.topOrBottom = $("select#topOrBottom").val();
		settings.numElements = $("select#number-elements").val();
		settings.aggregateFn = $("select#aggregate-fn").val();
		settings.timePeriod = $("select#time-period").val();
		
		settings.chartTitle = settings.topOrBottom.substr(0, 1).toUpperCase() + settings.topOrBottom.substr(1) 
								+ ' ' + settings.numElements + ' : ' + settings.seriesTitle 
								+ ' ' + settings.aggregateFn + ' over ' + settings.timePeriod + ' hrs in '
								+  $("select#group-name option:selected").text();
		
		//console.log('Gadget #' + gadgetInstanceId + ' - Saved settings: ' + printSettings(settings));
		uptimeGadget.saveSettings(settings).then(onGoodSave, onBadAjax);
	}
	
	function loadSettings(settings) {
		console.log('Gadget #' + gadgetInstanceId + ' - Loaded settings: ' + printSettings(settings));
	
		showEditPanel();
		
		metricType = settings.metricType;
		chartType = settings.chartType;
		metricValue = settings.metricValue;
		objectValue = settings.objectValue;
		elementValue = settings.elementValue;
		refreshInterval = settings.refreshInterval;
		chartTitle = settings.chartTitle;
		
		groupId = settings.groupId;
		topOrBottom = settings.topOrBottom;
		numElements = settings.numElements;
		aggregateFn = settings.aggregateFn;
		timePeriod = settings.timePeriod;

		if (metricType == "servicemonitor") {									
			if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting metric type to: '
						    + metricType)};	
			$("#service-monitor-metrics-btn").trigger('click');						
		}
		if (metricType == "performance") {									
			if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting metric type to: '
						    + metricType)};
			$("#performance-metrics-btn").trigger('click');	
		}
		if (metricType == "vmware") {									
			if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting metric type to: '
						    + metricType)};
			$("#vmware-metrics-btn").trigger('click');	
		}
		if (chartTitle == "") {
			$("#chart-title-btn").removeClass('active');
		}
	}
	
	function goodLoad(settings) {
		clearStatusBar();
		if (settings) {
			loadSettings(settings);
			displayChart(settings);
		} else if (uptimeGadget.isOwner()) { // What does this do?
			$('#widgetChart').hide();
			showEditPanel();
		}
	}
	
	function onGoodSave() {
		clearStatusBar();
		displayChart(settings);
	}
	
	function launchDivs() {
		$("#options-div").show();
		
		if (typeof refreshInterval !== 'undefined') {
			if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting refresh interval droptown to: '
						    + refreshInterval)};
			$.each(refreshIntervalSliderOptions, function(k, v) {
				if (v == refreshInterval) {
					if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting refreshRateSlider to '
						    + refreshIntervalOptions[refreshIntervalSliderOptions[k]])};
					$("#refreshIntervalSlider").slider("option", "value", k);
					$("#refreshIntervalLabelContents").text('Every '
							+ refreshIntervalOptions[refreshIntervalSliderOptions[k]]);
				}
			});
		}
		if (typeof chartType !== 'undefined') {
			if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Setting chart type to: '
						    + chartType)};
			if (chartType == 'areaspline') {
				$("#area-btn").trigger('click');
			}
			if (chartType == 'spline') {
				$("#line-btn").trigger('click');
			}
		}
		
		$("#buttonDiv").show();
	}
	
	function printSettings(settings) {
		var printString = 'metricType: ' + settings.metricType + ', metricValue: ' + settings.metricValue
				+ ', elementValue: ' + settings.elementValue + ', portValue: ' + settings.portValue
				+ ', objectValue: ' + settings.objectValue 
				+ ', refreshInterval: ' + settings.refreshInterval + ', chartType: ' + settings.chartType
				+ ', chartTitle: ' + settings.chartTitle + ', seriesTitle: ' + settings.seriesTitle
				+ ', groupId: ' + settings.groupId + ', topOrBottom: ' + settings.topOrBottom
				+ ', numElements: ' + settings.numElements + ', aggregateFn: ' + settings.aggregateFn
				+ ', timePeriod: ' + settings.timePeriod;
		return printString;
	}

	function displayChart(settings) {
		if (myChart) {
			myChart.stopTimer();
			myChart.destroy();
			myChart = null;
		}
	
		$("#widgetChart").show();
		$("#widgetChart").empty();
		$("#graph-div").show("fade", 600);
		
		if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Graph refresh rate: '
					    + (settings.refreshInterval / 1000) + ' seconds')};
		
		renderChart(settings);
		clearInterval(interval);
		interval = setInterval(function() {
			renderChart(settings, renderSuccessful)
			}, settings.refreshInterval);
	}
	
	function renderChart(settings) {
		$("#closeSettings").button('loading');
		$("#widgetBody").slideUp();
		$("#loading-div").show('fade');
		
		var options = {
			chart: {
				renderTo: 'widgetChart',
				type: 'bar'
			},
			legend: {
				enabled: false
			},
			title: {
				text: ""
			},
			credits: {
				enabled: false
			},
			xAxis: {
				categories:[]
			}
			,
			yAxis: {
				title: {
					enabled: false,
					text: ""
				}
			},
			series: [{
				data: []
			}]
		};
		
		requestString = getMetricsPath + '?call=getMetrics' + '&groupId=' + settings.groupId
						+ '&timePeriod=' + settings.timePeriod + '&aggregateFn=' + settings.aggregateFn
						+ '&metric=' + settings.metricValue + '&topOrBottom=' + settings.topOrBottom
						+ '&numElements=' + settings.numElements;
						
		
		
		if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Requesting: ' + requestString)};

		$.ajax({url: requestString,
			dataType: 'json'},
			function(data) {})
			.done (function( data ) {
			
				
			
				if (data.length < 1) {
					errorMessage = "There isn't enough monitoring data available for this metric and time period.";
					displayErrorWithRequest(errorMessage,requestString);
					showEditPanel();
					$("#closeSettings").button('reset');
					$("#widgetBody").slideDown();
					$("#loading-div").hide('fade');
				} else {if (debugMode) {console.log('Gadget #' + gadgetInstanceId + ' - Response: '
							    + JSON.stringify(data))};
								
					$.each(data, function(index, element) {

						options.series[0].name = settings.seriesTitle;
						options.xAxis.categories.push(element.name);
						options.series[0].data.push(element.value);					
						options.title.text = settings.chartTitle;					

					});					
				}
				
				var chart = new Highcharts.Chart(options);
					$("#closeSettings").button('reset');
					$("#loading-div").hide('fade');
					$("#widgetSettings").slideUp();
					$("#alertModal").modal('hide');
				
				
				})
			.fail (function(jqXHR, textStatus, errorThrown) {
				errorMessage = 'HTTP Status Code ' + jqXHR.status;
				displayErrorWithRequest(errorMessage,requestString);
				showEditPanel();
				$("#closeSettings").button('reset');
				$("#loading-div").hide('fade');
			});
	}
		
	function displayError(errorMessage) {
		console.log('Gadget #' + gadgetInstanceId + ' - Error: ' + errorMessage);
		$("#alertModalBody").empty();
		$("#alertModalBody").append('<p class="text-danger"><strong>Error:</strong> ' + errorMessage + '</p>');
		$("#alertModal").modal('show');
	}
	
	function displayErrorWithRequest(errorMessage,requestString) {
		console.log('Gadget #' + gadgetInstanceId + ' - Error: ' + errorMessage);
		$("#alertModalBody").empty();
		$("#alertModalBody").append('<p class="text-danger"><strong>Error:</strong> ' + errorMessage + '</p>'
					    + 'Here is the request string which has resulted in this error:'
					    + '<br><blockquote>' + requestString + '</blockquote>');
		$("#alertModal").modal('show');
	}
	// Static functions
	function displayStatusBar(msg) {
		gadgetDimOn();
		var statusBar = $("#statusBar");
		statusBar.empty();
		var errorBox = uptimeErrorFormatter.getErrorBox(msg);
		errorBox.appendTo(statusBar);
		statusBar.slideDown();
	}
	function clearStatusBar() {
		gadgetDimOff();
		var statusBar = $("#statusBar");
		statusBar.slideUp().empty();
	}
	function resizeGadget(dimensions) {
		myChartDimensions = toMyChartDimensions(dimensions);
		if (myChart) {
			myChart.resize(myChartDimensions);
		}
		$("#widgetChart").height($(window).height());
	}
	function toMyChartDimensions(dimensions) {
		return new UPTIME.pub.gadgets.Dimensions(Math.max(100, dimensions.width - 5), Math.max(100, dimensions.height));
	}
	function onBadAjax(error) {
		displayStatusBar(error, "Error Communicating with up.time");
	}
	function gadgetDimOn() {
		$.each(divsToDim, function(i, d) {
			var div = $(d);
			if (div.is(':visible') && div.css('opacity') > 0.6) {
				div.fadeTo('slow', 0.3);
			}
		});
	}
	function gadgetDimOff() {
		$.each(divsToDim, function(i, d) {
			var div = $(d);
			if (div.is(':visible') && div.css('opacity') < 0.6) {
				div.fadeTo('slow', 1);
			}
		});
	}
