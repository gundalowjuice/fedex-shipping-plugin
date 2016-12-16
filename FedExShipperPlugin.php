<?php

namespace Craft;

class FedExShipperPlugin extends BasePlugin
{

    private $_shippingMethods;

    /**
     * Called after the plugin class is instantiated; do any one-time initialization here such as hooks and events:
     *
     * craft()->on('entries.saveEntry', function(Event $event) {
     *    // ...
     * });
     *
     * or loading any third party Composer packages via:
     *
     * require_once __DIR__ . '/vendor/autoload.php';
     *
     * @return mixed
     */
    public function init()
    {   

        $this->_shippingMethods = craft()->plugins->getPlugin('fedexshipper')->getSettings()->shippingMedhods;
        
        // throw error if settings page is not filled out ?
        // todo: developer
        // if( !$this->getSettings() )
        //    throw new HttpException(406,'FedEx setting must be filled out! Go to Settings->FedExShipper');


        // add address validation before shipping method can be save
        // craft()->on('commerce_addresses.onBeforeSaveAddress', function($event){

        //     $address = $event->params['address'];

        //     // if zip, city or state is empty throw error

        //     if( empty($address->zipCode) ) {

        //         $address->addError('zipCode', Craft::t('Zip code is required.'));
        //         $event->performAction = false;
        //     }

        //     if( empty($address->city) ) {

        //         $address->addError('city', Craft::t('City is required.'));
        //         $event->performAction = false;
        //     }

        //     if( empty($address->stateValue) ) {

        //         $address->addError('state', Craft::t('State is required.'));
        //         $event->performAction = false;
        //     }
        // });
    }


    /**
     * Returns the user-facing name.
     *
     * @return mixed
     */
    public function getName()
    {
         return Craft::t('FedExShipper');
    }

    /**
     * Plugins can have descriptions of themselves displayed on the Plugins page by adding a getDescription() method
     * on the primary plugin class:
     *
     * @return mixed
     */
    public function getDescription()
    {
        return Craft::t('Provides shipping methods');
    }

    /**
     * Returns the version number.
     *
     * @return string
     */
    public function getVersion()
    {
        return '1';
    }

    /**
     * As of Craft 2.5, Craft no longer takes the whole site down every time a plugin’s version number changes, in
     * case there are any new migrations that need to be run. Instead plugins must explicitly tell Craft that they
     * have new migrations by returning a new (higher) schema version number with a getSchemaVersion() method on
     * their primary plugin class:
     *
     * @return string
     */
    public function getSchemaVersion()
    {
        return '1';
    }

    /**
     * Returns the developer’s name.
     *
     * @return string
     */
    public function getDeveloper()
    {
        return 'Kevin Douglass';
    }

    /**
     * Returns the developer’s website URL.
     *
     * @return string
     */
    public function getDeveloperUrl()
    {
        return '#';
    }

    /**
     * @return bool
     */
    public function hasCpSection()
    {
        return false;
    }

    /**
     * Defines the attributes that model your plugin’s available settings.
     *
     * @return array
     */
    protected function defineSettings()
    {
        return array(
            // shipper info
            'shipperName'     => [AttributeType::String, 'label' => 'Shipper Name', 'default' => '', 'required' => true],
            'shipperCompany'  => [AttributeType::String, 'label' => 'Shipper Company', 'default' => '', 'required' => true],
            'shipperAddress1' => [AttributeType::String, 'label' => 'Shipper Address 1', 'default' => '', 'required' => true],
            'shipperAddress2' => [AttributeType::String, 'label' => 'Shipper Address 2', 'default' => '', 'required' => false],
            'shipperCity'     => [AttributeType::String, 'label' => 'Shipper City', 'default' => '', 'required' => true],
            'shipperState'    => [AttributeType::String, 'label' => 'Shipper State', 'default' => '', 'required' => true],
            'shipperZip'      => [AttributeType::String, 'label' => 'Shipper ZIP', 'default' => '', 'required' => true],
            'shipperPhone'    => [AttributeType::String, 'label' => 'Shipper Phone', 'default' => '', 'required' => true],
            // shipping methods
            'shippingMedhods' => [AttributeType::String, 'label' => 'Shipping Methods', 'default' => '', 'required' => true],
            // api keys
            'apiKey'           => [AttributeType::String, 'label' => 'Key', 'default' => '', 'required' => true],
            'apiPswrd'         => [AttributeType::String, 'label' => 'Password', 'default' => '', 'required' => true],
            'apiMeter'         => [AttributeType::String, 'label' => 'Meter Number', 'default' => '', 'required' => true],
            'apiActNum'        => [AttributeType::String, 'label' => 'Account Number', 'default' => '', 'required' => true],
            'apiIntId'         => [AttributeType::String, 'label' => 'Integrator ID', 'default' => '', 'required' => true],
            'apiClientProdId'  => [AttributeType::String, 'label' => 'Client Product ID', 'default' => '', 'required' => true],
            'apiClientProdVer' => [AttributeType::String, 'label' => 'Client Product Version', 'default' => '', 'required' => true],
            // adjustments
            'fedexresidentialfee' => [AttributeType::String, 'label' => 'Residential Fee', 'default' => '', 'required' => false],
        );
    }

    /**
     * Returns the HTML that displays your plugin’s settings.
     *
     * @return mixed
     */
    public function getSettingsHtml()
    {
       return craft()->templates->render('fedexshipper/FedExShipper_Settings', array(
           'settings' => $this->getSettings()
       ));
    }

    /**
     * If you need to do any processing on your settings’ post data before they’re saved to the database, you can
     * do it with the prepSettings() method:
     *
     * @param mixed $settings  The Widget's settings
     *
     * @return mixed
     */
    public function prepSettings($settings)
    {
        // Modify $settings here...
        //craft()->cache->delete('allCarrierAccounts');

        return $settings;
    }


    /**
     * Returns the shipping methods available for the current order, or just list the base shipping accounts.
     *
     * @param Commerce_OrderModel|null $order
     *
     * @return array
     */   
    public function commerce_registerShippingMethods($order = null)
    {     
    
        // create array for all the fedex shipping options
        $shippers = [];

        if( $order ) {
      
            foreach( $this->_shippingMethods as $shipper ) { 
                  
                // calculate shipping
                // remove all spaces and uppercase to match FedEx
                $method = strtoupper(preg_replace('/\s+/', '_', $shipper)); 
                $rate   = craft()->fedExShipper_rate->getShippingRates($method, $order);
    
                // if there's not an error, return the value and shipping type
                if( $rate['type'] != 'error' ) {
                    
                    // visual shipping method to user. displays shipping method and price
                    $shippers[] = new FedExShipper_ShippingMethodModel( $shipper, $rate );
                }
            }
    
            return $shippers;
        }
        
        // only displayed in the control panel
        // really don't need the class instaniation because I could just return a list of carriers we are using
        foreach( $this->_shippingMethods as $shipper ) {
            
            $shippers[] = new FedExShipper_ShippingMethodModel( $shipper );
        }
        
        return $shippers;
    }
}