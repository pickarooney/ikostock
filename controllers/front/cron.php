<?php

class IkostockCronModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        // Call the cron process method
        $module = Module::getInstanceByName('ikostock');
        if ($module && method_exists($module, 'cronProcess')) {
            $module->cronProcess();
            echo 'Cron job executed successfully.';
        } else {
            echo 'Failed to execute cron job.';
        }
    }
}
