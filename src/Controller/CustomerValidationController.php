<?php

namespace CustomerValidation\Controller;

use Configuration;
use Context;
use Customer;
use Db;
use Exception;
use Mail;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController as AbstractAdminController;

use PrestaShop\PrestaShop\Core\Domain\Customer\Query\GetCustomerForEditing;

use PrestaShop\PrestaShop\Core\Domain\Customer\Command\EditCustomerCommand;





class CustomerValidationController extends AbstractAdminController

{

    public function toggleIsValidate($customerId)

    {
            $customer = new Customer($customerId);


            /**Maj de l'état actif du client */
            $sql = 'UPDATE '._DB_PREFIX_.'customer SET active = 1 WHERE id_customer = '.$customerId;
    
            Db::getInstance()->execute($sql);
            /** */
            $insert = 'INSERT INTO '._DB_PREFIX_.'customervalidation_validation (id_customer, validation) 
                        VALUES ('.$customerId.',1)';
            Db::getInstance()->execute($insert);
            /**Création du mail */
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
                'customer_activate',
                'Customer Active',
                $template_vars,
                $customer->email,
                null,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_.'customervalidation/mails/fr/customer_activate.html'
            );
            /** */
            
            $response = [
                'status' => true,
                'message' => $this->trans('C est bon.', 'Admin.Notifications.Success'),
            ];
        

        return $this->redirectToRoute('admin_customers_index');

    }

}