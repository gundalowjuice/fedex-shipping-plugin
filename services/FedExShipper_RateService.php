<?php

namespace Craft;

class FedExShipper_RateService extends BaseApplicationComponent
{

    private $_order;

    private $_settings;

    private $_shipmentByType;

    /**
     * This function can literally be anything you want, and you can have as many service functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     craft()->fedExShipper_RateService->exampleService()
     */
    public function init()
    {
        $this->_shipmentByType = [];
    }

    public function getZone(Commerce_OrderModel $order)
    {   
        $this->_settings = craft()->plugins->getPlugin('fedexshipper')->getSettings();
        $this->_order = $order;

        return $this->_callFedEx('FEDEX_GROUND');
    }

    public function getShippingRates($shipperType, Commerce_OrderModel $order)
    {

        // get unique signature. helps keep track of updates to order
        $signature = $this->_getSignature($order, $shipperType);

        // check to see if we have current shipping method of type on this request
        if (isset($this->_shipmentByType[$signature]) && $this->_shipmentByType[$signature] != false) {

            return $this->_shipmentByType[$signature];
        }

        // user defined settings
        $this->_settings = craft()->plugins->getPlugin('fedexshipper')->getSettings();
        // current order
        $this->_order = $order;

        // create cache key to prevent duplicated request
        $cacheKey = 'shipment-'.$signature;
        $shipment = craft()->cache->get($cacheKey);

        // if not cached, call the FedEx api
        if( !$shipment ) {

            // fedex api request
            // shippType are the different carrier options [ ground, 2 day, overnight, etc ]
            $shipment = $this->_callFedEx( $shipperType );

            // create cache array of each shipping type
            $this->_shipmentByType[$signature] = craft()->cache->set($cacheKey, $shipment);
        }

        $this->_shipmentByType[$signature] = $shipment;

        return $this->_shipmentByType[$signature];
    }

    private function _getSignature(Commerce_OrderModel $order, $shipperType)
    {   

        $totalQty        = $order->getTotalQty();
        $totalWeight     = $order->getTotalWeight();
        $totalWidth      = $order->getTotalWidth();
        $totalHeight     = $order->getTotalHeight();
        $totalLength     = $order->getTotalLength();
        $totalLength     = $order->getTotalLength();
        $shippingAddress = Commerce_AddressRecord::model()->findById($order->shippingAddressId);
        $updated = "";
        if ($shippingAddress)
        {
            $updated = DateTimeHelper::toIso8601($shippingAddress->dateUpdated);
        }

        return md5($shipperType.$totalQty.$totalWeight.$totalWidth.$totalHeight.$totalLength.$updated);
    }

    private function _callFedEx( $shipperType )
    {

        // SOAP Client
        $path_to_wsdl = __DIR__ ."/../wsdl/RateService_v20.wsdl";
        ini_set("soap.wsdl_cache_enabled", "0");
        $client = new \SoapClient($path_to_wsdl, array('trace' => 1)); // Refer to http://us3.php.net/manual/en/ref.soap.php for more information

        // FedEx request
        $request['WebAuthenticationDetail'] = array(
            'UserCredential' => array(
                'Key'      => $this->_settings->apiKey, 
                'Password' => $this->_settings->apiPswrd
            )
        ); 

        $request['ClientDetail'] = array(
            'AccountNumber' => $this->_settings->apiActNum,
            'MeterNumber'   => $this->_settings->apiMeter
        );

       $request['TransactionDetail'] = array('CustomerTransactionId' => ' *** Rate Available Services Request using PHP ***');

       $request['Version'] = array('ServiceId' => 'crs', 'Major' => '20', 'Intermediate' => '0', 'Minor' => '0');

        /*
            FedEx Express information will include the day and date the package will be delivered, based on the ship date you specified. 
            The FedEx Ground response will describe the number of business days required for the package delivery.
        */
        $request['ReturnTransitAndCommit'] = false;

        /*
            Identifies the method by which the package is to be tendered to FedEx.
            Regular Pickup - The shipper already has an every-day pickup scheduled with a courier.
            Request Courier - The shipper will call FedEx to ask for a courier.
            Drop Box - The shipper will drop the package in a FedEx drop box.
            Business Service Center - The shipper will drop off the package at an authorized FedEx business service center.
            Station - The shipper will drop off the package at a FedEx Station.
        */
        $request['RequestedShipment']['DropoffType'] = 'REGULAR_PICKUP'; 

        // Ship time and date
        $request['RequestedShipment']['ShipTimestamp'] = date('c');

        // See config for all options 
        // https://support.volusion.com/hc/en-us/articles/208370498-FedEx-Service-Codes-for-Your-Volusion-Store
        $request['RequestedShipment']['ServiceType'] = $shipperType; 

        // Descriptive data identifying the party responsible for shipping the package. Shipper and Origin should have the same address.
        $request['RequestedShipment']['Shipper'] = $this->_getShipperAddress();

        // Descriptive data identifying the party receiving the package.
        $request['RequestedShipment']['Recipient'] = $this->_getRecipientAddress();

        // Identifies the method of payment for a service
        $request['RequestedShipment']['ShippingChargesPayment'] = $this->_getServiceMethodOfPayment();
    
        /*
            One or more package-attribute descriptions, each of which describes an individual package, 
            a group of identical packages, or (for the total-piece-total-weight case) 
            common characteristics all packages in the shipment.
        */
        $lineItems = $this->_getLineItems( $this->_order );
        $request['RequestedShipment']['RequestedPackageLineItems'] = $lineItems;

        // The total number of packages in the entire shipment. Even when the shipment spans multiple transactions       
        // todo: developer not really sure of this functionality                          
        $request['RequestedShipment']['PackageCount'] = 1;//count($lineItems);


        try {

            if( $this->_setEndpoint('changeEndpoint') ){
                $newLocation = $client->__setLocation($this->setEndpoint('endpoint'));
            }
            
            $response = $client->getRates($request);

            // keep for testing
            //echo '#################################################################################';
            //print_r($response);
            //echo '#################################################################################';
            //echo "<table>\n";
            //$this->_printPostalDetails($response , "");
            //echo "</table>\n";

            // fedex shipping zone based on zips
            $zone = $response->RateReplyDetails->RatedShipmentDetails->ShipmentRateDetail->RateZone;


            if( $response -> HighestSeverity != 'FAILURE' && $response -> HighestSeverity != 'ERROR' ){

                $rateReply = $response->RateReplyDetails;
      
                if( is_array($response->RateReplyDetails) || is_object($response->RateReplyDetails) ) {

                    if( $rateReply->RatedShipmentDetails && is_array($rateReply->RatedShipmentDetails) ) {
                        $amount = array( 
                            'type'   => 'success', 
                            'amount' => $rateReply->RatedShipmentDetails[0]->ShipmentRateDetail->TotalNetCharge->Amount,
                            'zone'   => $zone
                        );
                    }elseif( $rateReply->RatedShipmentDetails && ! is_array($rateReply->RatedShipmentDetails) ) {
                        $amount = array( 
                            'type'   => 'success', 
                            'amount' => $rateReply->RatedShipmentDetails->ShipmentRateDetail->TotalNetCharge->Amount,
                            'zone'   => $zone
                        );

                        // how to format numbers in php, if needed
                        //number_format($rateReply->RatedShipmentDetails->ShipmentRateDetail->TotalNetCharge->Amount,2,".",","),
                    }

                }else{
                 
                    $amount = array( 'type' => 'error', 'message' => $response->Notifications->Message); 
                }
            }else{

                $amount = array( 'type' => 'error', 'message' => $response->Notifications->Message);
            }

        } catch (SoapFault $exception) {

           //printFault($exception, $client);  

           $amount = $exception->faultstring;    
        }

        $this->_writeToLog($response);

        return $amount;
    }

    private function _setEndpoint( $var )
    {
        if($var == 'changeEndpoint') Return false;
        if($var == 'endpoint') Return 'XXX';
    }

    private function _getServiceMethodOfPayment()
    {
        $paymentMethod = array(
            'PaymentType' => 'SENDER',
            'Payor' => array(
                'ResponsibleParty' => array(
                    'AccountNumber' => $this->_settings->apiActNum,
                    'Contact' => null,
                    'Address' => array(
                        'CountryCode' => 'US'
                    )
                )
            )
        );

        return $paymentMethod;
    }

    private function _getShipperAddress()
    {   
        // set some vars from settings
        $name       = $this->_settings['shipperName'];
        $company    = $this->_settings['shipperCompany'];
        $street     = $this->_settings['shipperAddress1'];
        $address2   = $this->_settings['shipperAddress2'];
        $city       = $this->_settings['shipperCity'];
        $state      = $this->_settings['shipperState'];
        $zip        = $this->_settings['shipperZip'];
        $phone      = $this->_settings['shipperPhone'];

        $address = array(
            'Contact' => array(
                'PersonName'  => $name,
                'CompanyName' => $company,
                'PhoneNumber' => $phone,
            ),
            'Address' => array(
                'StreetLines'         => array($street),
                'City'                => $city,
                'StateOrProvinceCode' => $state,
                'PostalCode'          => $zip,
                'CountryCode'         => 'US',
            )
        );

        return $address;
    }

    private function _getRecipientAddress()
    {   

        $shippingAddress = $this->_order->shippingAddress;

        // stop if shipping address is empty
        if( empty($shippingAddress) ) return;

        //$country = $arr->shipToCountry;
        $street  = $shippingAddress->address1;
        $city    = $shippingAddress->city;
        $state   = $this->_getStateAbbeviation($shippingAddress->state);
        $zip     = $shippingAddress->zipCode;

        $address = array(
            'Contact' => array(
                'PersonName' => '',
                'CompanyName' => '',
                'PhoneNumber' => '',
            ),
            'Address' => array(
                'StreetLines'         => array($street),
                'City'                => $city,
                'StateOrProvinceCode' => $state,
                'PostalCode'          => $zip,
                'CountryCode'         => 'US',
                //'Residential'         => $shippingAddress->businessName ? false : true,
            )
        );

        return $address;
    }

    private function _getLineItems( $order )
    {
        /*
            How to get total of cart items
            $cartTotalItems = $this->_order->totalQty;
        */

        // tracks our cur qty len and if it's divisible by 4

        $qty_loop           = 0; 
        $left_overs         = [];
        $box_weight_7_pack  = 7.5;
        $box_weight_14_pack = 15;
        $box_weight_21_pack = 25;
        $box_weight_28_pack = 32;
        $box_dimension_sm   = array( 'Length' => '14', 'Width'  => '12', 'Height' => '9.5', 'Units'  => 'IN' );
        $box_dimension_lg   = array( 'Length' => '15.5', 'Width'  => '15.5', 'Height' => '12.5', 'Units'  => 'IN' );

        // testing vars
        $test         = false;
        $lg_box_count = 0;
        $other_count  = 0;

        // get every line item
        foreach( $order->lineItems as $item ) {

            // get qty of a line item
            $qty_loop = $item->qty;

            // loop through each line item "qty"
            for( $i = 0; $i < $item->qty; $i++ ) {

                // do not run if out of box/package qty 
                if( $qty_loop > 0 ) {

                    // if the product is divisible by 4, then we can put up to 4 orders( 28 juices ) in one box
                    if( $qty_loop % 4 == 0 ) {
                                     
                        $lg_box_count++; // for testing
    
                        $packageLineItem[] = array(
                            'SequenceNumber'   => 1,
                            'GroupPackageCount'=> 1,
                            'Weight' => array(
                                'Value' => $box_weight_28_pack, 
                                'Units' => 'LB'
                            ),
                            'Dimensions' => $dimension_lg
                        );
    
                        // subract 4 from the $qty_loop since these products have been packaged
                        // we'll check the $qty_loop minus the 4 we just packed, to see if the new $qty_loop is divsible by 4 again
                        $qty_loop-=4;
    
                    }else{
                
                        // cur $qty_loop is not divisible by 4
                        // add cur $item to an array so at the very end we can package the left over products
                        // remove cur item
                        $left_overs[] = $item;
    
                        // we'll check the $qty_loop minus the 1 we just removed, to see if the new $qty_loop is divsible by 4 again
                        $qty_loop--;
                    }
                }
            }
        }

        // loop through our leftovers ( should never be more than 3 )
        // figure out whether we can put leftovers in sm or lg box and set the weight depending on the box size
        $left_over_len = count($left_overs);
     
        if( $left_over_len != 0 ) {

            $other_count++; // for testing

            if( $left_over_len == 3 ) {
                $w = $box_weight_21_pack;
            }elseif( $left_over_len == 2 ) {
                $w = $box_weight_14_pack;
            }else{
                $w = $box_weight_7_pack;
            }


            $d = ( $left_over_len == 3 ) ? $box_dimension_lg : $box_dimension_sm;

            $packageLineItem[] = array(
                'SequenceNumber'   => 1,
                'GroupPackageCount'=> 1,
                'Weight' => array(
                    'Value' => $w, 
                    'Units' => 'LB'
                ),
                'Dimensions' => $d
            );
        }

        if( $test ) {
            echo $w . ' : left over weight<br>';
            echo $left_over_len . ' : left over len <br>';
            echo $other_count . ' : left over box count <br>';
            echo $lg_box_count . ' : lg count <br>';
        }
       
        // 57
   
        return $packageLineItem;
    }

    private function _getStateAbbeviation( $state )
    {
        $states = array(
            'AL'=>'ALABAMA',
            'AK'=>'ALASKA',
            'AZ'=>'ARIZONA',
            'AR'=>'ARKANSAS',
            'CA'=>'CALIFORNIA',
            'CO'=>'COLORADO',
            'CT'=>'CONNECTICUT',
            'DE'=>'DELAWARE',
            'DC'=>'DISTRICT OF COLUMBIA',
            'FL'=>'FLORIDA',
            'GA'=>'GEORGIA',
            'HI'=>'HAWAII',
            'ID'=>'IDAHO',
            'IL'=>'ILLINOIS',
            'IN'=>'INDIANA',
            'IA'=>'IOWA',
            'KS'=>'KANSAS',
            'KY'=>'KENTUCKY',
            'LA'=>'LOUISIANA',
            'ME'=>'MAINE',
            'MD'=>'MARYLAND',
            'MA'=>'MASSACHUSETTS',
            'MI'=>'MICHIGAN',
            'MN'=>'MINNESOTA',
            'MS'=>'MISSISSIPPI',
            'MO'=>'MISSOURI',
            'MT'=>'MONTANA',
            'NE'=>'NEBRASKA',
            'NV'=>'NEVADA',
            'NH'=>'NEW HAMPSHIRE',
            'NJ'=>'NEW JERSEY',
            'NM'=>'NEW MEXICO',
            'NY'=>'NEW YORK',
            'NC'=>'NORTH CAROLINA',
            'ND'=>'NORTH DAKOTA',
            'OH'=>'OHIO',
            'OK'=>'OKLAHOMA',
            'OR'=>'OREGON',
            'PA'=>'PENNSYLVANIA',
            'RI'=>'RHODE ISLAND',
            'SC'=>'SOUTH CAROLINA',
            'SD'=>'SOUTH DAKOTA',
            'TN'=>'TENNESSEE',
            'TX'=>'TEXAS',
            'UT'=>'UTAH',
            'VT'=>'VERMONT',
            'VA'=>'VIRGINIA',
            'WA'=>'WASHINGTON',
            'WV'=>'WEST VIRGINIA',
            'WI'=>'WISCONSIN',
            'WY'=>'WYOMING',
        );

        foreach( $states as $abbr => $name ) {
        
            if( strtoupper( $state ) == $name ) {

                return $abbr;
            }
        }

        return false;
    }

    /**
     * SOAP request/response logging to a file
     */                                  
    private function _writeToLog($response){  
    
        if( !$logfile = fopen(__DIR__.'/fedextransactions.log', "a") ) {
            exit(1);
        }

        fwrite($logfile, sprintf("\r%s:- %s",date("D M j G:i:s T Y"), print_r($response, TRUE) ."\r\n\r\n"));

        fclose($logfile);
    }

    /*
        FedEx print outs
    */
    private function _printString($spacer, $key, $value)
    {
        if(is_bool($value)){
            if($value)$value='true';
            else $value='false';
        }
        echo '<tr><td>'.$spacer. $key .'</td><td>'.$value.'</td></tr>';
    }

    private function _printPostalDetails($details, $spacer)
    {
        foreach($details as $key => $value){
            if(is_array($value) || is_object($value)){
                $newSpacer = $spacer. '&nbsp;&nbsp;&nbsp;&nbsp;';
                echo '<tr><td>'. $spacer . $key.'</td><td>&nbsp;</td></tr>';
                $this->_printPostalDetails($value, $newSpacer);
            }elseif(empty($value)){
                $this->_printString($spacer, $key, $value);
            }else{
                $this->_printString($spacer, $key, $value);
            }
        }
    }
}