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
<div align="center" class="hikashop_raypay_end" id="hikashop_raypay_end">
	<span id="hikashop_raypay_end_message" class="hikashop_raypay_end_message">
		<?php echo JText::sprintf('لطفا صبر کنید', $this->payment_name) . '<br/>' . JText::_('اگر به درگاه منتقل نشدید کلیک کنید'); ?>
	</span>
    <span id="hikashop_raypay_end_spinner" class="hikashop_raypay_end_spinner hikashop_checkout_end_spinner">
	</span>
    <script type="text/javascript">window.location = "<?php echo $this->payment_params->url; ?>";</script>
</div>
