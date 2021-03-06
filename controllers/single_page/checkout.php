<?php
namespace Concrete\Package\VividStore\Controller\SinglePage;
use Page;
use PageController;
use Core;
use \Concrete\Core\Localization\Service\CountryList;
use View;
use Package;
use User;
use UserInfo;
use Session;

use \Concrete\Package\VividStore\Src\VividStore\Orders\Order as VividOrder;
use \Concrete\Package\VividStore\Src\VividStore\Cart\Cart as VividCart;
use \Concrete\Package\VividStore\Src\VividStore\Payment\Method as PaymentMethod;

defined('C5_EXECUTE') or die(_("Access Denied."));
class Checkout extends PageController
{
    public function view()
    {
        if(VividCart::getTotalItemsInCart() == 0){
            $this->redirect("/cart/");
        }
        $this->set('form',Core::make("helper/form"));
        $this->set("countries",Core::make('helper/lists/countries')->getCountries());
        $this->set("states",Core::make('helper/lists/states_provinces')->getStates());
        $this->set('subtotal',VividCart::getSubTotal());
        $this->set('taxtotal',VividCart::getTaxTotal());
        $this->set('shippingtotal',VividCart::getShippingTotal());
        $this->set('total',VividCart::getTotal());
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
        $this->addFooterItem("
            <script type=\"text/javascript\">
                vividStore.loadViaHash();
            </script>
        ");
        $this->set("enabledPaymentMethods",PaymentMethod::getEnabledMethods());
        
        
    }  
    
    public function failed()
    {
        $this->set('paymentErrors',Session::get('paymentErrors'));
        $this->view();   
    }
    public function submit()
    {
        $data = $this->post();
        
        //process payment
        $pmHandle = $data['payment-method'];
        $pm = PaymentMethod::getByHandle($pmHandle);
        if(!($pm instanceof PaymentMethod)){
            //There was no payment method enabled somehow.
            //so we'll force invoice.
            $pm = PaymentMethod::getByHandle('invoice');
        }
        $payment = $pm->submitPayment();
        if($payment['error']==1){
            $pmsess = Session::get('paymentMethod');  
            $pmsess[$pm->getPaymentMethodID()] = $data['payment-method'];
            Session::set('paymentMethod',$pmsess);
            $pesess = Session::get('paymentErrors');
            $pesess = $payment['errorMessage'];
            Session::set('paymentErrors',$pesess);
            $this->redirect("/checkout/failed#payment");
        } else {
            VividOrder::add($data,$pm);    
            $this->redirect('/checkout/complete');
        }  
    }
    public function validate()
    {
        
    }

}    