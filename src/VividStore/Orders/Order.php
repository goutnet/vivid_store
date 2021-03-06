<?php 
namespace Concrete\Package\VividStore\src\VividStore\Orders;

use Concrete\Core\Foundation\Object as Object;
use Database;
use File;
use User;
use UserInfo;
use Core;
use Package;
use Concrete\Core\Mail\Service as MailService;
use Session;

use \Concrete\Package\VividStore\Src\VividStore\Utilities\Price as Price;
use \Concrete\Package\VividStore\Src\Attribute\Key\StoreOrderKey as StoreOrderKey;
use \Concrete\Package\VividStore\Src\VividStore\Cart\Cart as VividCart;
use \Concrete\Package\VividStore\Src\VividStore\Orders\Item as OrderItem;
use \Concrete\Package\VividStore\Src\Attribute\Value\StoreOrderValue as StoreOrderValue;
use \Concrete\Package\VividStore\Src\VividStore\Payment\Method as PaymentMethod;

defined('C5_EXECUTE') or die(_("Access Denied."));
class Order extends Object
{
    public static function getByID($oID) {
        $db = Database::get();
        $data = $db->GetRow("SELECT * FROM VividStoreOrder WHERE oID=?",$oID);
        if(!empty($data)){
            $order = new Order();
            $order->setPropertiesFromArray($data);
        }
        return($order instanceof Order) ? $order : false;
    }  
    public function getCustomersMostRecentOrderByCID($cID)
    {
        $db = Database::get();
        $data = $db->GetRow("SELECT * FROM VividStoreOrder WHERE cID=? ORDER BY oID DESC",$cID); 
        return Order::getByID($data['oID']);
    }
    public function add($data,$pm)
    {
        $db = Database::get();
        
        //get who ordered it
        $u = new User();
        $uID = $u->getUserID();
        $ui = UserInfo::getByID($uID);
        
        //what time is it?
        $dt = Core::make('helper/date');
        $now = $dt->getLocalDateTime();
        
        //get the price details
        $shipping = VividCart::getShippingTotal();
        $tax = VividCart::getTaxTotal();
        $total = VividCart::getTotal();
        
        //get payment method
        $pmID = $pm->getPaymentMethodID();
        
        //add the order
        $vals = array($uID,$now,'pending',$pmID,$shipping,$tax,$total);
        $db->Execute("INSERT INTO VividStoreOrder(cID,oDate,oStatus,pmID,oShippingTotal,oTax,oTotal) values(?,?,?,?,?,?,?)", $vals);
        $oID = $db->lastInsertId();
        $order = Order::getByID($oID);
        $order->setAttribute("billing_first_name",$ui->getAttribute("billing_first_name"));
        $order->setAttribute("billing_last_name",$ui->getAttribute("billing_last_name"));
        $order->setAttribute("billing_address",$ui->getAttribute("billing_address"));
        $order->setAttribute("billing_phone",$ui->getAttribute("billing_phone"));
        $order->setAttribute("shipping_first_name",$ui->getAttribute("shipping_first_name"));
        $order->setAttribute("shipping_last_name",$ui->getAttribute("shipping_last_name"));
        $order->setAttribute("shipping_address",$ui->getAttribute("shipping_address"));
        
        //add the order items
        $cart = Session::get('cart');
        foreach($cart as $cartItem){
            OrderItem::add($cartItem,$oID);    
        }
        
        //add user to Store Customers group
        $group = \Group::getByName('Store Customer');
        if (is_object($group) || $group->getGroupID() < 1) {
            $u->enterGroup($group);
        }
        
        //send out the alerts
        $mh = new MailService();
        $pkg = Package::getByHandle('vivid_store');
        $pkgconfig = $pkg->getConfig();
        $fromEmail = $pkgconfig->get('vividstore.emailalerts');
        if(!$fromEmail){
            $fromEmail = "store@".$_SERVER['SERVER_NAME'];
        }
        $alertEmails = explode(",", $pkgconfig->get('vividstore.notificationemails'));
        $alertEmails = array_map('trim',$alertEmails);
        
                    
            //receipt
            $mh->from($fromEmail);
            $mh->to($ui->getUserEmail());
            $mh->addParameter("order", $order);
            $mh->load("order_receipt","vivid_store");
            $mh->sendMail();
            
            //order notification
            $mh->from($fromEmail);
            foreach($alertEmails as $alertEmail){
                $mh->to($alertEmail);
            }
            $mh->addParameter("order", $order);
            $mh->load("new_order_notification","vivid_store");
            $mh->sendMail();
            
        
        Session::set('cart',null);
    }
    public function remove()
    {
        $db = Database::get();
        $db->Execute("DELETE from VividStoreOrder WHERE oID=?",$this->oID);
        $db->Execute("DELETE from VividStoreOrderItem WHERE oID=?",$this->oID);
    }
    public function getOrderItems()
    {
        $db = Database::get();    
        $rows = $db->GetAll("SELECT * FROM VividStoreOrderItem WHERE oID=?",$this->oID);
        $items = array();
        foreach($rows as $row){
            $items[] = OrderItem::getByID($row['oiID']);
        }
        return $items;
    }
    public function getOrderID(){ return $this->oID; }
    public function getPaymentMethodName() {
        $pm = PaymentMethod::getByID($this->pmID); 
        if(is_object($pm)){  
            return $pm->getPaymentMethodName();
        }
    }
    public function getStatus(){ return $this->oStatus; }
    public function getCustomerID(){ return $this->cID; }
    public function getOrderDate(){ return $this->oDate; }
    public function getTotal() { return $this->oTotal; }
    public function getSubTotal()
    {
        $items = $this->getOrderItems();
        $subtotal = 0;
        if($items){
            foreach($items as $item){
                $subtotal = $subtotal + ($item['oiPricePaid'] * $item['oiQty']);
            }
        }
        return $subtotal;
    }
    public function getTaxTotal() { return $this->oTax; }
    public function getShippingTotal() { return $this->oShippingTotal; }
    
    public function updateStatus($status)
    {
        Database::get()->Execute("UPDATE VividStoreOrder SET oStatus = ? WHERE oID = ?",array($status,$this->oID));
    }
    public function setAttribute($ak, $value)
    {
        if (!is_object($ak)) {
            $ak = StoreOrderKey::getByHandle($ak);
        }
        $ak->setAttribute($this, $value);
    }
    public function getAttribute($ak, $displayMode = false) {
        if (!is_object($ak)) {
            $ak = StoreOrderKey::getByHandle($ak);
        }
        if (is_object($ak)) {
            $av = $this->getAttributeValueObject($ak);
            if (is_object($av)) {
                return $av->getValue($displayMode);
            }
        }
    }
    public function getAttributeValueObject($ak, $createIfNotFound = false) {
        $db = Database::get();
        $av = false;
        $v = array($this->getOrderID(), $ak->getAttributeKeyID());
        $avID = $db->GetOne("select avID from VividStoreOrderAttributeValues where oID = ? and akID = ?", $v);
        if ($avID > 0) {
            $av = StoreOrderValue::getByID($avID);
            if (is_object($av)) {
                $av->setOrder($this);
                $av->setAttributeKey($ak);
            }
        }
        
        if ($createIfNotFound) {
            $cnt = 0;
        
            // Is this avID in use ?
            if (is_object($av)) {
                $cnt = $db->GetOne("select count(avID) from VividStoreOrderAttributeValues where avID = ?", $av->getAttributeValueID());
            }
            
            if ((!is_object($av)) || ($cnt > 1)) {
                $av = $ak->addAttributeValue();
            }
        }
        
        return $av;
    }
}