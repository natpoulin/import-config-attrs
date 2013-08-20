<?php

/**
 * This class imports configurable attributes from Dataflow Profile import. 
 *
 * Useful pages to look at:
 * http://www.magentocommerce.com/knowledge-base/entry/magento-for-dev-part-1-introduction-to-magento
 * http://www.magentocommerce.com/wiki/how_to_-_import_manufacturers_or_any_other_option_attribute_set
 * http://www.magentocommerce.com/wiki/5_-_modules_and_development/catalog/programmatically_adding_attributes_and_attribute_sets
 * http://blog.onlinebizsoft.com/magento-programmatically-insert-new-attribute-option/
 * http://www.chrisshennan.com/2011/04/23/import-product-attributes-into-magento/
 * http://blog.magikcommerce.com/importing-manufactures-option-attribute-sets-in-magento/
 * http://www.magentocommerce.com/wiki/3_-_store_setup_and_management/import_export/how_to_automatically_import_simple_grouped_and_configurable_products
 * 
 * The important changes to the Action XML are go mks adapter = importattributes/convert_adapter_attribute
 * You can put method = parse but right now Magento actually ignores the contents of method.
 *
 * The Action XML should look like this:
 * <action type="dataflow/convert_adapter_io" method="load">
 *  <var name="type">file</var>
 *  <var name="path">var/import</var>
 *  <var name="filename"><![CDATA[filename.csv]]></var>
 *  <var name="format"><![CDATA[csv]]></var>
 * </action>
 * 
 * <action type="dataflow/convert_parser_csv" method="parse">
 *     <var name="delimiter"><![CDATA[,]]></var>
 *     <var name="enclose"><![CDATA["]]></var>
 *     <var name="fieldnames">true</var>
 *     <var name="store"><![CDATA[0]]></var>
 *     <var name="adapter">importattributes/convert_adapter_attribute</var>
 *     <var name="method">parse</var>
 * </action>
 */
class Peanut_Importattributes_Model_Convert_Adapter_Attribute
    extends Mage_Eav_Model_Convert_Adapter_Entity
{
    const MULTI_DELIMITER   = ' , ';
    const ENTITY            = 'catalog_product_import';
	const ALL_ATTR_SETS		= '***ALL_SETS***';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'catalog_product_import';

    /**
     * product attribute set collection array
     *
     * @var array
     */
    protected $_productAttributeSets;
	
	protected $_attributeSetGroups;

    protected $_attributes = array();
	
	protected $_productEntityTypeId;
	
	protected $_stores;
	
	/**
     * Affected entity ids
     *
     * @var array
     */
    protected $_affectedEntityIds = array();
 
	/**
     * Retrieve event prefix for adapter
     *
     * @return string
     */
    public function getEventPrefix()
    {
        return $this->_eventPrefix;
    }
	
    /**
     * Store affected entity ids
     *
     * @param  int|array $ids
     * @return Mage_Catalog_Model_Convert_Adapter_Product
     */
    protected function _addAffectedEntityIds($ids)
    {
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $this->_addAffectedEntityIds($id);
            }
        } else {
            $this->_affectedEntityIds[] = $ids;
        }

        return $this;
    }
	
	
    /**
     * Retrieve affected entity ids
     *
     * @return array
     */
    public function getAffectedEntityIds()
    {
        return $this->_affectedEntityIds;
    }

    /**
     * Clear affected entity ids results
     *
     * @return Mage_Catalog_Model_Convert_Adapter_Product
     */
    public function clearAffectedEntityIds()
    {
        $this->_affectedEntityIds = array();
        return $this;
    }

	protected function getProductEntityTypeId()
    {
        if (is_null($this->_productEntityTypeId)) {
            $this->_productEntityTypeId = Mage::getModel('eav/entity')
                ->setType('catalog_product')
                ->getTypeId();
		}
		return $this->_productEntityTypeId;
	}
	
   /**
     * Retrieve product attribute set groups collection array
     *
     * @return array
     */
    protected function getProductAttributeSetGroups($setId)
    {
        if (is_null($this->_attributeSetGroups))
            $this->_attributeSetGroups = array();
			
		if (!isset($this->_attributeSetGroups[$setId])) {
			$this->_attributeSetGroups[$setId] = array();
            $groupCollection = Mage::getResourceModel('eav/entity_attribute_group_collection')
                ->setAttributeSetFilter($setId);
            foreach ($groupCollection as $group) {
                $this->_attributeSetGroups[$setId][$group->getAttributeGroupName()] = $group->getId();
            }
        }
        return $this->_attributeSetGroups[$setId];
    }
	
    /**
     * Retrieve product attribute set collection array
     *
     * @return array
     */
    protected function getProductAttributeSets()
    {
        if (is_null($this->_productAttributeSets)) {
            $this->_productAttributeSets = array();

            $entityTypeId = $this->getProductEntityTypeId();
            $collection = Mage::getResourceModel('eav/entity_attribute_set_collection')
                ->setEntityTypeFilter($entityTypeId);
            foreach ($collection as $set) {
                $this->_productAttributeSets[$set->getAttributeSetName()] = $set->getId();
            }
        }
        return $this->_productAttributeSets;
    }
	
    /**
     *  Init stores
     */
    protected function _initStores ()
    {
        if (is_null($this->_stores)) {
            $this->_stores = Mage::app()->getStores(true, true);
            foreach ($this->_stores as $code => $store) {
                $this->_storesIdCode[$store->getId()] = $code;
            }
        }
    }

    /**
     * Retrieve store object by code
     *
     * @param string $store
     * @return Mage_Core_Model_Store
     */
    public function getStoreByCode($store)
    {
        $this->_initStores();
        /**
         * In single store mode all data should be saved as default
         */
        if (Mage::app()->isSingleStoreMode()) {
            return Mage::app()->getStore(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
        }

        if (isset($this->_stores[$store])) {
            return $this->_stores[$store];
        }

        return false;
    }

    /**
     * Retrieve store object by code
     *
     * @param string $store
     * @return Mage_Core_Model_Store
     */
    public function getStoreById($id)
    {
        $this->_initStores();
        /**
         * In single store mode all data should be saved as default
         */
        if (Mage::app()->isSingleStoreMode()) {
            return Mage::app()->getStore(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
        }

        if (isset($this->_storesIdCode[$id])) {
            return $this->getStoreByCode($this->_storesIdCode[$id]);
        }

        return false;
    }
	
    /**
     * Load product collection Id(s)
     */
    public function load()
    {
		Mage::log("Peanut_Importattributes_Model_Convert_Adapter_Attribute starting load");
		
        $attrFilterArray = array();
        $attrFilterArray ['label']			= 'like';
        $attrFilterArray ['attribute_code']	= 'startsWith';
        $attrFilterArray ['is_global']		= 'eq';
        $attrFilterArray ['is_required']	= 'eq';
        $attrFilterArray ['is_configurable']= 'eq';
        $attrFilterArray ['apply_to']		= 'eq';
        $attrFilterArray ['value']			= 'startsWith';
        $attrFilterArray ['attribute_set']	= 'eq';

        $attrToDb = array(
            'label'          => 'frontend_label',
            'attribute_set' => 'attribute_set_id'
        );

        $filters = $this->_parseVars();

        parent::setFilter($attrFilterArray, $attrToDb);

        return parent::load();
    }

	/**
	 * Parse a file and import attributes in it.
	 */
    public function parse()
    {
        $batchModel = Mage::getSingleton('dataflow/batch');

        $batchImportModel = $batchModel->getBatchImportModel();
        $importIds = $batchImportModel->getIdCollection();

        foreach ($importIds as $importId) {
            $batchImportModel->load($importId);
            $importData = $batchImportModel->getBatchData();

            $this->saveRow($importData);
        }
    }

    public function save()
    {
		Mage::throwException("Peanut_Importattributes_Model_Convert_Adapter_Attribute save not implemented");
        return $this;
    }

	/**
	 * Find the store for the attribute, error if no store.
	 **/
	protected function getImportDataStore($importData)
	{
		if (!isset($importData['store']) or empty($importData['store'])) 
		{
            if (!is_null($this->getBatchParams('store'))) 
			{
                $store = $this->getStoreById($this->getBatchParams('store'));
            } 
			else 
			{
                Mage::throwException('Skip import row, required field "store" not defined');
            }
        }
        else 
		{
            $store = $this->getStoreByCode($importData['store']);
        }
		
		if ($store === false) 
		{
			Mage::log('Skip import row, store "'.$importData['store'].'" field not exists');
            Mage::throwException('Skip import row, store "'.$importData['store'].'" field not exists');	
		}
		
		return $store;
	}
	
	/**
	 * Extract the possible values from a string
	 * comma delimited for each store view and | pipe delimited for each option. 
	 * format e.g storeID:value,storeID:value1 or 
	 * 0:value1,0:value2|1:value1,2:value2 where 0 and 1 are 2 store IDs
	 **/
	protected function getImportDataValues($importData)
	{
		$attrOptions = array();
		if (isset($importData['values']) and $importData['values'])
		{
			$attrOptions = explode('|', $importData['values']);
		}
		return $attrOptions;
	}
	
	/**
	 * Check the importData for the product type(s) the attribute applies to.
	 * Valid product types: simple, grouped, configurable, virtual, bundle, downloadable, giftcard
	 **/
	protected function getImportDataProductTypes($importData)
	{
		$validTypes = array ('simple', 'grouped', 'configurable', 'virtual', 'bundle', 'downloadable', 'giftcard');
		
		$productTypes = array();
		if (isset($importData['product_types']) and $importData['product_types'])
		{
			$importProductTypes = explode(',', $importData['product_types']);
			foreach ($importProductTypes as $productType)
			{
				if (in_array($productType, $validTypes))
					$productTypes[] = $productType;
			}
		}
		return $productTypes;
	}

	/**
	 * Check if the attribute code is valid.
	 * It must begin with a lowercase letter and contain only letters, underscores, and numbers.
	 * It cannot be longer than 254 characters.
	 **/	
	protected function validateAttributeCode($code)
	{
		//validate attribute_code
		if ($code) 
		{
			$validatorAttrCode = new Zend_Validate_Regex(array('pattern' => '/^[a-z][a-z_0-9]{1,254}$/'));
			if ($validatorAttrCode->isValid($code)) 
				return true;
		}
		Mage::log("Attribute code '$code' is invalid. Please use only letters (a-z), numbers (0-9) or underscore(_) in this field, first character should be a letter.");
		Mage::throwException("Attribute code '$code' is invalid. Please use only letters (a-z), numbers (0-9) or underscore(_) in this field, first character should be a letter.");
	}
	
	/**
	 * Return the set_ids for the attribute_sets. If the set doesn't exist, error.
	 */
	protected function getAttributeSetIds($attributeSets)
	{
		if (!$attributeSets or empty($attributeSets))
			return array();
			
		$sets = $this->getProductAttributeSets();
		
		if (self::ALL_ATTR_SETS == $attributeSets[0])
			return $sets;
			
		$ids = array();
		foreach ($attributeSets as $attributeSet)
		{
			if (!isset($sets[$attributeSet]))
			{
				Mage::log("Attribute set $attributeSet does not exist.");
				Mage::throwException("Attribute set $attributeSet does not exist.");
			}
			$ids[$attributeSet] = $sets[$attributeSet];
		}
		return $ids;
	}
	
	/**
	 * Return the set_ids for the group. If the group doesn't exist, error.
	 */
	protected function getGroupIds($setIds, $groupName)
	{
		if (is_null($setIds) or empty($setIds) or !$groupName)
			return array();
		
		$groupIds = array();
		foreach ($setIds as $setId)
		{
			$groups = $this->getProductAttributeSetGroups($setId);

			if (isset($groups[$groupName]))
			{
				$groupIds[$setId] = $groups[$groupName];
			}
			//else
			//	Mage::log("Group $groupName does not exist on attribute set id $setId.");
			// Mage::throwException("Group $groupName does not exist on attribute set id $setId.");
				
		}
		return $groupIds;
	}
	
	/**
	 * Create an attribute, return id.
	 * Right now it will always be a dropdown
	 */
	protected function createAttribute($labelText, $attributeCode, $productTypes, $attributeSetIds, $groupIds)
	{
		// Build the data structure that will define the attribute. See 
		// Mage_Adminhtml_Catalog_Product_AttributeController::saveAction().

		$data = array(
						'is_global'                     => '1',
						'frontend_input'                => 'select',
						'default_value_text'            => '',
						'default_value_yesno'           => '0',
						'default_value_date'            => '',
						'default_value_textarea'        => '',
						'is_unique'                     => '0',
						'is_required'                   => '0',
						'frontend_class'                => '',
						'is_searchable'                 => '0',
						'is_visible_in_advanced_search' => '0',
						'is_comparable'                 => '1',
						'is_used_for_promo_rules'       => '0',
						'is_html_allowed_on_front'      => '1',
						'is_visible_on_front'           => '1',
						'used_in_product_listing'       => '0',
						'used_for_sort_by'              => '0',
						'is_configurable'               => '1',
						'is_filterable'                 => '0',
						'is_filterable_in_search'       => '0',
						'backend_type'                  => 'int',
						'default_value'                 => '',
						'is_user_defined'				=> '1',
					);
					
		
		$data['apply_to']       = $productTypes;
		$data['attribute_code'] = $attributeCode;
		$data['frontend_label'] = array(
											0 => $labelText,
											1 => '',
											3 => '',
											2 => '',
											4 => '',
										);
			
		$model = Mage::getModel('catalog/resource_eav_attribute');

		$model->addData($data);

		$entityTypeID = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
		$model->setEntityTypeId($entityTypeID);

		$model->setIsUserDefined(1);

		// Save.
		$model->save();

		if(!is_null($groupIds) and !empty($groupIds))
		{
			foreach ($groupIds as $setId => $groupId)
			{
				$model->setAttributeSetId($setId)
						->setAttributeGroupId($groupIds[$setId])
						->save();
			}
		}
		
		$attributeId = $model->getId();

		Mage::log("Attribute [$labelText] has been saved as ID ($attributeId).");
		return $attributeId;
	}
	
    /**
     * Save attribute (import)
	 * If the does not exist, create it. If it exists update options.
     *
     * @param  array $importData
     * @throws Mage_Core_Exception
     * @return bool
     */
    public function saveRow(array $importData)
    {
		$store = $this->getImportDataStore($importData);
		
		$labelText = isset($importData['label']) ? $importData['label'] : '';
        $attributeCode = isset($importData['attribute_code']) ? $importData['attribute_code'] : '';

        if($labelText == '' or $attributeCode == '')
        {
			Mage::log("Can't import the attribute with an empty label or code.  LABEL= [$labelText]  CODE= [$attributeCode]");
            Mage::throwException("Can't import the attribute with an empty label or code.  LABEL= [$labelText]  CODE= [$attributeCode]");
        }

		$this->validateAttributeCode($attributeCode);
	
		$values = $this->getImportDataValues($importData);
		$productTypes = $this->getImportDataProductTypes($importData);

		$attributeSets = isset($importData['attribute_set']) ? explode('|', $importData['attribute_set']) : array();
		$groupSet = isset($importData['group_set']) ? $importData['group_set'] : '';
				
		if(!empty($attributeSets) and !$groupSet)
		{
			Mage::log("$attributeCode has attribute_set $attributeSet but needs a group_set to subscribe to it.");
			Mage::throwException("$attributeCode has attribute_set $attributeSet but a group_set to subscribe to it.");
		}
		else if ($groupSet and empty($attributeSets))
		{
			Mage::log("$attributeCode has group_set $groupSet but needs a attribute_set to subscribe to it.");
			Mage::throwException("$attributeCode has group_set $groupSet but needs a attribute_set to subscribe to it.");
		}
		// get set_id for attribute_set, if doesn't exist error
		// if groupSet doesn't exist, error
		$attributeSetIds = $this->getAttributeSetIds($attributeSets);
		$groupIds = $this->getGroupIds($attributeSetIds, $groupSet);
		
		// check if new attribute or existing
		$attrId = Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $attributeCode);
		
		if ($attrId === false)
		{
			$attrId = $this->createAttribute($labelText, $attributeCode, $productTypes, $attributeSetIds, $groupIds);
		}
		else if (!empty($groupIds))
		{
			$attribute =  Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', $attributeCode);
			foreach ($groupIds as $setId => $groupId)
			{
				$attribute->setAttributeSetId($setId)
							->setAttributeGroupId($groupIds[$setId])
							->save();
			}
		}
		
		if (!empty($values))
		{
			$attribute =  Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', $attributeCode);

			/* @var $attribute Mage_Eav_Model_Entity_Attribute */
			$eoptions = $attribute->getSource()->getAllOptions(false);
			// build existing options array so we do not duplicate the options
			$existingOptions = array();
			foreach($eoptions as $opt){
				$existingOptions[$opt['label']] = $opt['value'];
			}

			$options = array('value' => array(), 'order' => array(), 'delete' => array());
			$i = 0;
			foreach($values as $option){
				if(!isset($existingOptions[$option])){
					$i++;
					$options['value']['option_' . $i] = array($option);
				}
			}

			if(count($options['value'])>0){
				$attribute->setOption($options);
				$attribute->save();
				Mage::log("Options successfully imported for $attributeCode : ".count($options['value']));
			} else {
				Mage::log( "No NEW options to import for $attributeCode.");
			}
		}
		
        return true;
    }

    /**
     * Silently save product (import)
     *
     * @param  array $importData
     * @return bool
     */
    public function saveRowSilently(array $importData)
    {
        try {
			Mage::log("Peanut_Importattribute_Model_Convert_Adapter_Attribute starting saveRowSilently");
            $result = $this->saveRow($importData);
            return $result;
        } catch (Exception $e) {
            return false;
        }
    }

}
