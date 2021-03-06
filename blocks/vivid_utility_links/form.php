<?php defined('C5_EXECUTE') or die("Access Denied.");?>

<div class="checkbox">
    <label>
        <?php echo $form->checkbox('showCartItems',1,isset($showCartItems)?$showCartItems:1);?>
        <?=t('Show Amount of Items in Cart')?>
    </label>
</div>

<div class="checkbox">
    <label>
        <?php echo $form->checkbox('showSignIn',1,isset($showSignIn)?$showSignIn:1);?>
        <?=t('Show Sign-in Link')?>
    </label>
</div>
<div class="form-group">
    <?php echo $form->label('cartLabel',t('Cart Link Label'));?>
    <?php echo $form->text('cartLabel',$cartLabel?$cartLabel:t("View Cart"));?>
</div>
<div class="form-group">
    <?php echo $form->label('itemsLabel',t('Cart Link Label'));?>
    <?php echo $form->text('itemsLabel',$itemsLabel?$itemsLabel:t("Items in Cart"));?>
</div>