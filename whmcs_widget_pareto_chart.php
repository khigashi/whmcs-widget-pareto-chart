<?php
/**
 * WHMCS Widget: Pareto Chart
 *
 * @author   Marcio Dias <khigashi.oang@gmail.com>
 * Version: 0.1
 * Release Date: 22/01/2017
 */

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");


function whmcs_widget_pareto_chart($vars) {
	Global $_ADMINLANG;

	//Config
	$config_client_limit = 10; //TOP Clients to Show + All Others
	$config_days = 90; //YTD days
	
	//Chart Vars
	$chartName = "chartCont";
    $currency = getCurrency("",$currency);
	$title = "Pareto Chart (Last ".$config_days." days)"; //Widget Title
	$date_range = date('Ymd',strtotime("-".$config_days." day"));
	$count = $all_others = $total_amount = $accum = 0;
	
	
	//Query Total Revenue in X days
	$query = "SELECT 
	tblclients.id, 
	tblclients.firstname, 
	tblclients.lastname, 
	tblclients.companyname, 
	SUM(((tblaccounts.amountin/tblaccounts.rate)-(tblaccounts.fees/tblaccounts.rate)-(tblaccounts.amountout/tblaccounts.rate)) - tblinvoices.credit) AS balance
	
	FROM tblaccounts 
	INNER JOIN tblinvoices ON tblaccounts.invoiceid=tblinvoices.id 
	INNER JOIN tblclients ON tblclients.id = tblaccounts.userid 
	
	WHERE DATE_FORMAT(tblinvoices.datepaid,'%Y%m%d') > '".$date_range."' AND tblinvoices.status='Paid'
	
	GROUP BY tblaccounts.userid ORDER BY balance DESC";


	$result = mysql_query($query);
	
	

	
	$arrayData = array();
	
	while(list($client_id, $client_first_name, $client_last_name, $client_company, $client_balance) = mysql_fetch_array($result)) {
	
		$client_company = trim($client_company);
		$client_first_name = trim($client_first_name);
		$client_last_name = trim($client_last_name);
		
		if(!$client_company){
			
			$clientName = $client_first_name;
			$clientName .= " ".$client_last_name;
	
		}else{
			
			$clientName = $client_company;
			
		}
		
		$total_amount = $total_amount + $client_balance;
		
		//If more than X clients, insert in "All Others" category
		if($count > $config_client_limit){
			
			$all_others = $all_others + $client_balance;
						
		}else{
			
			$arrayData[$count]['title'] = trim($clientName);
			$arrayData[$count]['value'] = $client_balance;
	
			$count++;
			
		}
	
	}
	
	$arrayData[$count]['title'] = "All Others";
	$arrayData[$count]['value'] = $all_others;
	
	
	
	//Insert the percent amount
	foreach($arrayData AS $key => $code){
		
		$calc = $accum + 100 * $code['value'] / $total_amount;
		$accum = $calc;
		
		$arrayData[$key]['pct'] = $calc;
	
	}
	
	
	
	
	foreach($arrayData AS $data_info){
	
		if(round($data_info['pct']) >= 80){
			$valor = array('v' => '#c476e4');
		}else{
			$valor = array('v' => '#8E44AD'); //Change bar color if % is less than 80%
		}
	
		$chartdata['rows'][] = array(
		'c' => array(
			array('v'=> $data_info['title']),
			array('v' => round($data_info['value'],2),'f' => formatCurrency($data_info['value'])),
			$valor,
			array('v' => round($data_info['pct']),'f' => round($data_info['pct'])."%"),
			array('v' => '#E74C3C'),
		));
	
		
	}
	
	
	//Cols
	$chartdata['cols'][] = array('label'=>'Client','type'=>'string');
	$chartdata['cols'][] = array('label'=>'Amount','type'=>'number');
	$chartdata['cols'][] = array( 'type' => 'string', 'role' => 'style');
	$chartdata['cols'][] = array('label'=>'Accum Amount Percent','type'=>'number');
	$chartdata['cols'][] = array( 'type' => 'string', 'role' => 'style');


	//Chart Options
	$options['colors'] = array("#9B59B6", "#E74C3C");
	
	$options['legend'] = array("position" => "targetAxisIndex");
	$options['chartArea'] = array("right" => 80, "left" => "80","top" => "40","width" => "85%","height" => "70%");

	
	$options['series'][0] = array('type' => "bars", "targetAxisIndex" => 0, 'pointSize' => 6, 'pointsVisible' => 1);
	$options['series'][1] = array('type' => "line", "targetAxisIndex" => 1, 'pointSize' => 6, 'pointsVisible' => 1);

	$options['vAxes'][] = array('title' => 'Amount', 'format' => $currency['prefix'].'#', 'minValue' => 0, 'maxValue' => 100);
	$options['vAxes'][] = array('title' => 'Percent', 'format' => '#\'%\'', 'minValue' => 0, 'maxValue' => 100);
	
	$options['hAxis'] = array('title' => 'Client');
	$options['focusTarget'] = 'category';
	$options['crosshair'] = array('trigger' => 'both', 'color' => '#C0C0C0');
	

	//Show chart
	$content .= '<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>';
	$content .= '<script type="text/javascript">';
	$content .= 'google.charts.load("current", {"packages":["corechart"]});';
	$content .= 'google.charts.setOnLoadCallback(draw'.$chartName.');';
	$content .= 'function draw'.$chartName.'() {';
	$content .= 'var jsonData = \''.json_encode($chartdata).'\';';
	$content .= 'var data = new google.visualization.DataTable(jsonData);';
	$content .= 'var options = '.json_encode($options).';';
	$content .= 'var chart = new google.visualization.ComboChart(document.getElementById("chartcont'.$chartName.'"));';
	$content .= 'chart.draw(data,options);';
	$content .= '}';
	$content .= '</script>';
	$content .= '<div id="chartcont'.$chartName.'" style="width:100%;height:400px;"></div>';
	
	return array(
		'title'=>$title,
		'content'=>$content
	);

}

add_hook("AdminHomeWidgets",1,"whmcs_widget_pareto_chart");
?>