<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\YesAndNoChoiceType;
use PrestaShop\PrestaShop\Core\Domain\Customer\Query\GetCustomerForEditing;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomerValidation extends Module
{
    public function __construct()
    {
        $this->name = 'customervalidation';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Doryan Fourrichon';
        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => _PS_VERSION_
        ];
        
        //récupération du fonctionnement du constructeur de la méthode __construct de Module
        parent::__construct();
        $this->bootstrap = true;

        $this->displayName = $this->l('Customer Validation');
        $this->description = $this->l('Module qui envoie des mails de validation');

        $this->confirmUninstall = $this->l('Do you want to delete this module');
    }

    public function install()
    {
        if(!parent::install() ||
        !Configuration::updateValue('ACTIVATE_VALIDATE_CUSTOMER', '') ||
        !Configuration::updateValue('GROUP_CUSTOMER', '') ||
        !$this->registerHook('actionCustomerAccountAdd') ||
        !$this->registerHook('actionAfterUpdateCustomerFormHandler') ||
        !$this->registerHook('actionCustomerGridDefinitionModifier') ||
        !$this->registerHook('actionCustomerGridQueryBuilderModifier') ||
        !$this->registerHook('actionCustomerFormBuilderModifier') ||
        !$this->registerHook('actionAfterCreateCustomerFormHandler') ||
        !$this->createTable()

        )
        {
            return false;
        }
            return true;
        
    }

    public function uninstall()
    {
        if(!parent::uninstall() ||
        !Configuration::deleteByName('ACTIVATE_VALIDATE_CUSTOMER') ||
        !Configuration::deleteByName('GROUP_CUSTOMER') ||
        !$this->unregisterHook('actionCustomerAccountAdd') ||
        !$this->unregisterHook('actionAfterUpdateCustomerFormHandler') ||
        !$this->unregisterHook('actionCustomerGridDefinitionModifier') ||
        !$this->unregisterHook('actionCustomerGridQueryBuilderModifier') ||
        !$this->unregisterHook('actionCustomerFormBuilderModifier') ||
        !$this->unregisterHook('actionAfterCreateCustomerFormHandler') ||
        !$this->deleteTable()
        )
        {
            return false;
        }
            return true;
    }

    public function getContent()
    {        
        return $this->postProcess().$this->renderForm();
    }

    public function renderForm()
    {
        $groups = Group::getGroups($this->context->language->id);

        $groups_list = array();

        foreach ($groups as $group) 
        {
            $groups_list[] = array(
                'id' => $group['id_group'],
                'name' => $group['name'],
            );
        }



        $field_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Setings'),
            ],
            'input' => [
                [
                    'type' => 'switch',
                        'label' => $this->l('Active Customer Validation'),
                        'name' => 'ACTIVATE_VALIDATE_CUSTOMER',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'label2_on',
                                'value' => 1,
                                'label' => $this->l('Oui')
                            ),
                            array(
                                'id' => 'label2_off',
                                'value' => 0,
                                'label' => $this->l('Non')
                            )
                        )
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('choose your group'),
                    'name' => 'GROUP_CUSTOMER',
                    'required' => true,
                    'options' => [
                        'query' => $groups_list,
                        'id' => 'id',
                        'name' => 'name'
                    ]
                ],
            ],
            'submit' => [
                'title' => $this->l('save'),
                'class' => 'btn btn-primary',
                'name' => 'saving'
            ]
        ];

        $helper = new HelperForm();
        $helper->module  = $this;
        $helper->name_controller = $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value['ACTIVATE_VALIDATE_CUSTOMER'] = Configuration::get('ACTIVATE_VALIDATE_CUSTOMER');
        $helper->fields_value['GROUP_CUSTOMER'] = Configuration::get('GROUP_CUSTOMER');

        return $helper->generateForm($field_form);
    }

    public function postProcess()
    {
        if(Tools::isSubmit('saving'))
        {
            Configuration::updateValue('ACTIVATE_VALIDATE_CUSTOMER',Tools::getValue('ACTIVATE_VALIDATE_CUSTOMER'));
            Configuration::updateValue('GROUP_CUSTOMER',Tools::getValue('GROUP_CUSTOMER'));

            return $this->displayConfirmation('Les champs sont enregistrées');
        }
    }


    public function createTable()
    {
        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS '._DB_PREFIX_.'customervalidation_validation(
                id_validation INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                id_customer INT UNSIGNED NOT NULL,
                validation BOOLEAN NOT NULL
            )');
    }

    public function deleteTable()
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS '._DB_PREFIX_.'customervalidation_validation'
        );
    }

    public function hookActionCustomerAccountAdd($params)
    {
        if(Configuration::get('ACTIVATE_VALIDATE_CUSTOMER') == 1)
        {
            $customer = $params['newCustomer'];

            $context = Context::getContext();
            $id_lang = (int) $context->language->id;
            $id_shop = (int) $context->shop->id;
            $configuration = Configuration::getMultiple(
                [
                'PS_SHOP_EMAIL',
                'PS_SHOP_NAME',
                'PS_SHOP_DOMAIN'
                ],$id_lang, null, $id_shop
            );
            $date = date('Y-m-d à H:i:s');
            
            $template_vars = [
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{date}' => $date,
                '{shop_name}' => $configuration['PS_SHOP_EMAIL'],
                '{shop_url}' => 'https://'.$configuration['PS_SHOP_DOMAIN'].'/'.$configuration['PS_SHOP_NAME']
            ];
            
            Mail::send(
                $id_lang,
                'new_customer',
                $this->l('New Customer'),
                $template_vars,
                $customer->email,
                null,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_.'customervalidation/mails/fr/new_customer.html'
            );


            Tools::redirect($this->context->link->getModuleLink('customervalidation','redirect'));
       
        }
    }

    
    public function hookActionAfterUpdateCustomerFormHandler(array $params)
    {
        $customer = $params['form_data'];
        if(Configuration::get('ACTIVATE_VALIDATE_CUSTOMER') == 1)
        {
            if((bool) $customer['default_group_id'] == (bool) Configuration::get('GROUP_CUSTOMER'))
            {
                if($customer['validation'] == 1)
                {
                    if((bool) $customer['is_enabled'] == 0)
                    {
                        $sql = 'UPDATE '._DB_PREFIX_.'customer SET active = 1 WHERE id_customer = '.$params['id'];
                        Db::getInstance()->execute($sql);

                        $this->updateCustomerValidation($params);
                    }

                    if($this->checkId($params['id']) == null)
                    {
                        $insert = 'INSERT INTO '._DB_PREFIX_.'customervalidation_validation (id_customer, validation) 
                        VALUES ('.$params['id'].','.$customer['validation'].')';
                        Db::getInstance()->execute($insert);
                    }
                    else
                    {
                        if($this->checkValidation($params['id']) == 1 || $this->checkValidation($params['id']) == 0)
                        {
                            $update = 'UPDATE '._DB_PREFIX_.'customervalidation_validation SET validation = '.$customer['validation'].' WHERE id_customer = '.$params['id'];
                            Db::getInstance()->execute($update);
                        }

                        if($this->checkValidation($params['id']) == 0)
                        {
                            $sql = 'UPDATE '._DB_PREFIX_.'customer SET active = 0 WHERE id_customer = '.$params['id'];
                            Db::getInstance()->execute($sql);
                        }
                        
                    }

                    
                        
                    
                }

                
            }
        }
        
    }


    public function hookActionCustomerGridDefinitionModifier(array $params)
    {
        if(Configuration::get('ACTIVATE_VALIDATE_CUSTOMER') == 1)
        {
            $definition = $params['definition'];

            $definition
                ->getColumns()
                ->addAfter(
                    'optin',
                    (new ToggleColumn('validation'))
                        ->setName($this->l('Validation'))
                        ->setOptions([
                            'field' => 'validation',
                            'primary_field' => 'id_customer',
                            'route' => 'customer_validation_toggle_is_validate',
                            'route_param_name' => 'customerId',
                        ])
                )
            ;

            $definition->getFilters()->add(
                (new Filter('validation',YesAndNoChoiceType::class))
                ->setAssociatedColumn('validation')
            );
        }
        
    }

    public function hookActionCustomerGridQueryBuilderModifier(array $params)
    {
        if(Configuration::get('ACTIVATE_VALIDATE_CUSTOMER') == 1)
        {
            $searchQueryBuilder = $params['search_query_builder'];

            $searchCriteria = $params['search_criteria'];

            $searchQueryBuilder->addSelect(
                'IF(dcur.`validation` IS NULL,0,dcur.`validation`) AS `validation`'
            );

            $searchQueryBuilder->leftJoin(
                'c',
                '`' . pSQL(_DB_PREFIX_) . 'customervalidation_validation`',
                'dcur',
                'dcur.`id_customer` = c.`id_customer`'
            );
            

            if('validation' === $searchCriteria->getOrderBy())
            {
                $searchQueryBuilder->orderBy('dcur.`validation`', $searchCriteria->getOrderWay());
            }

            foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
                if ('validation' === $filterName) {
                    $searchQueryBuilder->andWhere('dcur.`validation` = :validation');
                    $searchQueryBuilder->setParameter('validation', $filterValue);

                    if (!$filterValue) {
                        $searchQueryBuilder->orWhere('dcur.`validation` IS NULL');
                    }
                }
            }
        }
        
    }

    public function hookActionCustomerFormBuilderModifier(array $params)
    {
        if(Configuration::get('ACTIVATE_VALIDATE_CUSTOMER') == 1)
        {
            $formBuilder = $params['form_builder'];
            $formBuilder->add('validation', SwitchType::class, [
                'label' => $this->l("Validation"),
                'required' => false
            ]);

            $customerId = $params['id'];

            $params['data']['validation'] = $this->getValidation($customerId);

            $formBuilder->setData($params['data']);
        }

    }

    private function getValidation($customerId)
    {
        return $this->checkValidation($customerId); 
    }

    public function hookActionAfterCreateCustomerFormHandler(array $params)
    {

    }

    private function checkId($id_customer)
    {
        $sql = 'SELECT * FROM '._DB_PREFIX_.'customervalidation_validation WHERE id_customer = '.$id_customer;
        return Db::getInstance()->executeS($sql);
    }

    private function checkValidation($id_customer)
    {
        $sql = 'SELECT `validation` FROM '._DB_PREFIX_.'customervalidation_validation WHERE id_customer = '.$id_customer;
        $result = Db::getInstance()->executeS($sql);

        return !empty($result) ? (int)$result[0]['validation'] : null;
    }

    private function updateCustomerValidation(array $params)
    {
        $customer = $params['form_data'];

        $context = Context::getContext();
        $id_lang = (int) $context->language->id;
        $id_shop = (int) $context->shop->id;
        $configuration = Configuration::getMultiple(
            [
            'PS_SHOP_EMAIL',
            'PS_SHOP_NAME',
            'PS_SHOP_DOMAIN'
            ],$id_lang, null, $id_shop
        );
        $date = date('Y-m-d à H:i:s');
        
        $template_vars = [
            '{firstname}' => $customer['first_name'],
            '{lastname}' => $customer['last_name'],
            '{date}' => $date,
            '{shop_name}' => $configuration['PS_SHOP_EMAIL'],
            '{shop_url}' => 'https://'.$configuration['PS_SHOP_DOMAIN'].'/'.$configuration['PS_SHOP_NAME']
        ];

        Mail::send(
            $id_lang,
            'customer_activate',
            $this->l('Customer Active'),
            $template_vars,
            $customer['email'],
            null,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_.'customervalidation/mails/fr/customer_activate.html'
        );
    }

}