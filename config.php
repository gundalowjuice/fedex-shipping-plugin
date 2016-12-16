<?php

/*

*/

return [
	
	// The address you will be posting from.
	'fromAddress' => [
		"shipperName"     => "",
		"shipperAddress1" => "",
		"shipperAddress2" => "",
		"shipperCity"     => "",
		"shipperState"    => "",
		"shipperZip"      => "",
		"shipperPhone"    => ""
	],
	'carrierAccounts' => [
		'fedex' => [
			'FIRST_OVERNIGHT'        => 'First Overnight',
			'PRIORITY_OVERNIGHT'     => 'Priority Overnight',
			'STANDARD_OVERNIGHT'     => 'Standard Overnight',
			'FEDEX_2_DAY_AM'         => 'FedEx 2 Day AM',
			'FEDEX_2_DAY'            => 'FedEx 2 Day',
			'FEDEX_EXPRESS_SAVER'    => 'FedEx Express Saver',
			'FEDEX_GROUND'           => 'FedEx Ground'
		]
	]
];

