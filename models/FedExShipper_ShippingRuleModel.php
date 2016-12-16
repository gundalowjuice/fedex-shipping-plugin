<?php
/**
 * FedExShipper plugin for Craft CMS
 *
 * FedExShipper_FedExRule Model
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

use Commerce\Interfaces\ShippingRule;

class FedExShipper_ShippingRuleModel extends BaseModel implements ShippingRule
{
    
    private $_price;

    private $_zone;

    // private $_order;

    public function __construct($price = null, $zone = null)
    {
        $this->_price = $price;

        $this->_zone = $zone;
    }
    /**
     * Returns the unique handle of this Shipping Rule.
     *
     * @return string
     */
    public function getHandle()
    {
        return 'ruleBase';
    }
    /**
     * Is this rule a match on the order? If false is returned, the shipping engine tries the next rule.
     *
     * @return bool
     */
    public function matchOrder(\Craft\Commerce_OrderModel $order)
    {
        // dump $order to see all data

        // instead of always showing this shipping method we can add additional logic
        // ex: only show if order price is > num

        // if address is residential add adjustment fee from settings. 
        // fedex Residential param of "1" alreadys adds additional fees, but it's a lot
        // so we can do it their way(lot more expensive and doesn't seem correct ) or just add the additional fee from settings
        $residentialAdustment = craft()->plugins->getPlugin('fedexshipper')->getSettings()->fedexresidentialfee;

        //if( empty($order->shippingAddress->businessName) ) $this->_price = $this->_price + $residentialAdustment;

        return true;
    }

    /**
     * Is this shipping rule enabled for listing and selection
     *
     * @return bool
     */
    public function getIsEnabled()
    {
        return true;
    }

    /**
     * Stores this data as json on the orders shipping adjustment.
     * When someone uses the shipping model, store the data in the cart
     *
     * @return mixed
     */
    public function getOptions()
    {
        return [ 'ruleBase' => true ];
    }

    /**
     * Returns the percentage rate that is multiplied per line item subtotal.
     * Zero will not make any changes.
     *
     * @return float
     */
    public function getPercentageRate()
    {
        return 0;
    }

    /**
     * Returns the flat rate that is multiplied per qty.
     * Zero will not make any changes.
     *
     * @return float
     */
    public function getPerItemRate()
    {
        return 0;
    }

    /**
     * Returns the rate that is multiplied by the line item's weight.
     * Zero will not make any changes.
     *
     * @return float
     */
    public function getWeightRate()
    {
        return 0;
    }

    /**
     * Returns a base shipping cost. This is added at the order level.
     * Zero will not make any changes.
     *
     * @return float
     */
    public function getBaseRate()
    {   

        return $this->_price;
    }

    /**
     * Returns a max cost this rule should ever apply.
     * If the total of your rates as applied to the order are greater than this, the baseShippingCost
     * on the order is modified to meet this max rate.
     *
     * @return float
     */
    public function getMaxRate()
    {
        return 0;
    }

    /**
     * Returns a min cost this rule should have applied.
     * If the total of your rates as applied to the order are less than this, the baseShippingCost
     * on the order is modified to meet this min rate.
     * Zero will not make any changes.
     *
     * @return float
     */
    public function getMinRate()
    {
        return 0;
    }

    /**
     * Returns a description of the rates applied by this rule;
     * Zero will not make any changes.
     *
     * @return string
     */
    public function getDescription()
    {
        return '';
    }
}