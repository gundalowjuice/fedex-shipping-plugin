<?php
/**
 * FedExShipper plugin for Craft CMS
 *
 * FedExShipper_FedExMethod Model
 *
 * --snip--
 * Models are containers for data. Just about every time information is passed between services, controllers, and
 * templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 * --snip--
 *
 * @author    Kevin Douglass
 * @copyright Copyright (c) 2016 Kevin Douglass
 * @link      http://kevindouglass.io
 * @package   FedExShipper
 * @since     1
 */

namespace Craft;

use Commerce\Interfaces\ShippingMethod;

class FedExShipper_ShippingMethodModel extends BaseModel implements ShippingMethod
{

    private $_price;

    private $_zone;

    private $_shppingType;

    public function __construct($shippinType, $price = null)
    {   

        $this->_price = $price['amount'];

        $this->_zone = $price['zone'];

        $this->_shppingType = $shippinType;
    }   

    /**
     * Returns the type of Shipping Method. This might be the name of the plugin or provider.
     * The core shipping methods have type: `Custom`. This is shown in the control panel only.
     *
     * @return string
     */
    public function getType()
    {
        return 'Custom';
    }

    /**
     * Returns the ID of this Shipping Method, if it is managed by Craft Commerce.
     *
     * @return int|null The shipping method ID, or null if it is not managed by Craft Commerce
     */
    public function getId()
    {
        return null;
    }

    /**
     * Returns the name of this Shipping Method as displayed to the customer and in the control panel.
     *
     * @return string
     */
    public function getName()
    {
        return $this->_shppingType;
    }

    /**
     * Returns the unique handle of this Shipping Method.
     *
     * @return string
     */
    public function getHandle()
    {   
        $name = preg_replace('/\s+/', '', $this->_shppingType, $this->_zone); // remove all spaces

        return 'fedEx' . strtolower($name);
    }

    /**
     * Returns the control panel URL to manage this method and it's rules.
     * An empty string will result in no link.
     *
     * @return string
     */
    public function getCpEditUrl()
    {
        return '';
    }

    /**
     * Returns an array of rules that meet the `ShippingRules` interface.
     *
     * @return \Commerce\Interfaces\ShippingRules[] The array of ShippingRules
     */
    public function getRules()
    {   

        // return multiple rules by initializing each class
        // use fedex look up api here
        return [

            new FedExShipper_ShippingRuleModel($this->_price, $this->_zone)
        ];
    }

    /**
     * Is this shipping method enabled for listing and selection by customers.
     *
     * @return bool
     */
    public function getIsEnabled()
    {
        return true;
    }

  
}