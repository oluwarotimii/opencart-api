<?php
class ControllerExtensionModuleMobileapi extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/mobileapi');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_mobileapi', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit']     = $this->language->get('text_edit');
        $data['entry_status']  = $this->language->get('entry_status');
        $data['entry_secret']  = $this->language->get('entry_secret');
        $data['entry_ttl']     = $this->language->get('entry_ttl');
        $data['help_secret']   = $this->language->get('help_secret');
        $data['help_ttl']      = $this->language->get('help_ttl');

        $data['button_save']   = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/mobileapi', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/mobileapi', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        // Load current config values
        if (isset($this->request->post['module_mobileapi_status'])) {
            $data['module_mobileapi_status'] = $this->request->post['module_mobileapi_status'];
        } else {
            $data['module_mobileapi_status'] = $this->config->get('module_mobileapi_status');
        }

        if (isset($this->request->post['module_mobileapi_secret'])) {
            $data['module_mobileapi_secret'] = $this->request->post['module_mobileapi_secret'];
        } else {
            $data['module_mobileapi_secret'] = $this->config->get('module_mobileapi_secret');
        }

        if (isset($this->request->post['module_mobileapi_ttl'])) {
            $data['module_mobileapi_ttl'] = $this->request->post['module_mobileapi_ttl'];
        } else {
            $data['module_mobileapi_ttl'] = $this->config->get('module_mobileapi_ttl');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/mobileapi', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/mobileapi')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}
