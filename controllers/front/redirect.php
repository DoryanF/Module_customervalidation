<?php

class CustomerValidationRedirectModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        if(Context::getContext()->customer->logged == 1)
        {
            $sql = 'UPDATE '._DB_PREFIX_.'customer SET active = 0 WHERE id_customer = '.Context::getContext()->customer->id;
            Db::getInstance()->execute($sql);
        }

        
        header("Refresh:1");
        
        return $this->setTemplate('module:customervalidation/views/templates/front/redirect.tpl');
    }
}