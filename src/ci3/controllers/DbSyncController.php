<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use DbSync\Http\Controllers\SyncController;

class DbSyncController extends CI_Controller {
    private $controller;

    public function __construct() {
        parent::__construct();
        $this->controller = new SyncController();
    }

    public function login() { $this->controller->login(); }
    public function logout() { $this->controller->logout(); }
    public function index() { $this->controller->index(); }
    public function process() { $this->controller->process(); }
    public function handleApi() { $this->controller->handleApi(); }
}
