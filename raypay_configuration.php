<?php
/**
 * RayPay payment plugin
 *
 * @developer hanieh729
 * @publisher RayPay
 * @package HikaShop
 * @subpackage payment
 * @copyright (C) 2021 RayPay
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * http://raypay.ir
 */
defined('_JEXEC') or die('Restricted access'); ?>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][user_id]"><?php
            echo JText::_('شناسه کاربری');
            ?></label>
    </td>
    <td>
        <input type="text" name="data[payment][payment_params][user_id]"
               value="<?php echo $this->escape(@$this->element->payment_params->user_id); ?>"/>
    </td>
</tr>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][acceptor_code]"><?php
            echo JText::_('کد پذیرنده');
            ?></label>
    </td>
    <td>
        <input type="text" name="data[payment][payment_params][acceptor_code]"
               value="<?php echo $this->escape(@$this->element->payment_params->acceptor_code); ?>"/>
    </td>
</tr>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][invalid_status]"><?php
            echo JText::_('INVALID_STATUS');
            ?></label>
    </td>
    <td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][invalid_status]', @$this->element->payment_params->invalid_status);
        ?></td>
</tr>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][pending_status]"><?php
            echo JText::_('PENDING_STATUS');
            ?></label>
    </td>
    <td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][pending_status]', @$this->element->payment_params->pending_status);
        ?></td>
</tr>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][verified_status]"><?php
            echo JText::_('VERIFIED_STATUS');
            ?></label>
    </td>
    <td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][verified_status]', @$this->element->payment_params->verified_status);
        ?></td>
</tr>