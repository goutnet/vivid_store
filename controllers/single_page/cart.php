<?php
namespace Concrete\Package\VividStore\Controller\SinglePage;

use Page;
use PageController;
use Package;
use Core;
use View;
use Session;

use \Concrete\Package\VividStore\Src\VividStore\Product\Product as VividProduct;
use \Concrete\Package\VividStore\Src\VividStore\Cart\Cart as VividCart;
use \Concrete\Package\VividStore\Src\VividStore\Utilities\Price as Price;

defined('C5_EXECUTE') or die(_("Access Denied."));
class Cart extends PageController
{
    public function view()
    {
        $this->set('cart',Session::get('cart')); 
        $this->set('total',VividCart::getSubTotal());
        $this->addHeaderItem("
            <script type=\"text/javascript\">
                var PRODUCTMODAL = '".View::url('/productmodal')."';
                var CARTURL = '".View::url('/cart')."';
                var CHECKOUTURL = '".View::url('/checkout')."';
            </script>
        ");
        $pkg = Package::getByHandle('vivid_store');
        $packagePath = $pkg->getRelativePath();
        $this->addFooterItem(Core::make('helper/html')->javascript($packagePath.'/js/vivid-store.js','vivid-store'));   
        $this->addHeaderItem(Core::make('helper/html')->css($packagePath.'/css/vivid-store.css','vivid-store'));       
    }  
    public function add()
    {
        $data = $this->post();
        VividCart::add($data);             
    }
    public function update()
    {
        $data = $this->post();
        VividCart::update($data);
    }
    public function remove()
    {
        $instanceID = $_POST['instance'];
        VividCart::remove($instanceID);
    }
    public function clear()
    {
        VividCart::clear();
        $this->view();
    }
}    