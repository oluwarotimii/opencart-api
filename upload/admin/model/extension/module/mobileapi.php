<?php
class ModelExtensionModuleMobileapi extends Model {
    public function install() {
        // no DB changes required – store default settings
        $this->load->model('setting/setting');
        $default = array(
            'module_mobileapi_status' => 1,
            'module_mobileapi_secret' => md5(mt_rand()),
            'module_mobileapi_ttl'    => 7200,
        );
        $this->model_setting_setting->editSetting('module_mobileapi', $default);
    }

    public function uninstall() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_mobileapi');
    }
}
