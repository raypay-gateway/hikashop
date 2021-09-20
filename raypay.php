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
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;

class plgHikashoppaymentRaypay extends hikashopPaymentPlugin
{
  public $accepted_currencies = ['IRR', 'TOM', 'IRT','TOMAN'];

  public $multiple = true;

  public $name = 'raypay';

  public $doc_form = 'raypay';

  /**
   * plgHikashoppaymentRaypay constructor.
   * @param $subject
   * @param $config
   * @param Http|null $http
   */
  public function __construct(&$subject, $config, Http $http = null)
  {
    $this->http = $http ?: HttpFactory::getHttp();
    parent::__construct($subject, $config);
  }

  /**
   * @return array
   */
  public function options()
  {
    $options = array('Content-Type' => 'application/json');
    return $options;
  }

  /**
   * @param $order
   * @param $do
   * @return bool
   */
  public function onBeforeOrderCreate(&$order, &$do)
  {
    if (parent::onBeforeOrderCreate($order, $do) === true) {
      return true;
    }

    if (empty($this->payment_params->user_id) || empty($this->payment_params->marketing_id)) {
      $this->app->enqueueMessage('لطفا شناسه کاربری و کد پذیرنده را برای پرداخت از طریق رای پی تنظیم کنید.');
      $do = false;
    }
  }

  /**
   * @param $order
   * @param $methods
   * @param $method_id
   * @return bool|void
   * @throws Exception
   */
  public function onAfterOrderConfirm(&$order, &$methods, $method_id)
  {
    parent::onAfterOrderConfirm($order, $methods, $method_id);

    //set information for request
    $user_id = $this->payment_params->user_id;
    $marketing_id = $this->payment_params->marketing_id;
    $sandbox = !($this->payment_params->sandbox == 'no');
    $desc = 'پرداخت هیکاشاپ ، سفارش شماره: ' . $order->order_id;
    $callback = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=' . $this->name .'&order_id=' . $order->order_id . '&tmpl=component&lang=' . $this->locale . $this->url_itemid;
    $invoice_id             = round(microtime(true) * 1000);

    //check amount
    $amount = round($order->cart->full_total->prices[0]->price_value_with_tax, (int)$this->currency->currency_locale['int_frac_digits']);
    if (empty($amount)) {
      $msg = 'خطا در محاسبه مبلغ تراکنش';
      $this->order_log($order->order_id, $msg);
      $cancel_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id=' . $order->order_id . $this->url_itemid;
      $app = JFactory::getApplication();
      $app->redirect($cancel_url, $msg, 'Error');
    }

    // Convert Currency
    $amount = $this->get_amount($amount, $this->currency->currency_code);

    // Customer information
    $billing = $order->cart->billing_address;
    $name = $billing->address_firstname . ' ' . $billing->address_lastname;
    $phone = $billing->address_telephone;
    $mail = $order->customer->user_email;

    //set params and send request
      $data = array(
          'amount'       => strval($amount),
          'invoiceID'    => strval($invoice_id),
          'userID'       => $user_id,
          'redirectUrl'  => $callback,
          'factorNumber' => strval($order->order_id),
          'marketingID' => $marketing_id,
          'email'        => $mail,
          'mobile'       => $phone,
          'fullName'     => $name,
          'comment'      => $desc,
          'enableSandBox'      => $sandbox
      );
      $this->order_log(json_encode($data)  , "data");
    $url  = 'https://api.raypay.ir/raypay/api/v1/Payment/pay';
	$options = array('Content-Type: application/json');
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HTTPHEADER,$options );
	$result = curl_exec($ch);
	$result = json_decode($result );
	$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
    //$options = $this->options();
    //$result = $this->http->post($url, json_encode($data, true), $options);
    //$result = json_decode($result->body);
    //$http_status = $result->StatusCode;

    //check http error
    if ($http_status != 200 || empty($result) || empty($result->Data)) {
      $msg = sprintf('خطا هنگام ایجاد تراکنش. کد خطا: %s - پیام خطا: %s', $http_status, $result->Message);
      $this->order_log($order->order_id, $msg);
      $app = JFactory::getApplication();
      $cancel_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id=' . $order->order_id . $this->url_itemid;
      $app->redirect($cancel_url, $msg, 'Error');
    }

    //save raypay invoice id in db(result id)
    $this->order_log($order->order_id, "raypay_invoice_id:$invoice_id");

    //redirect to result

      $token = $result->Data;
      $link='https://my.raypay.ir/ipg?token=' . $token;
      $this->payment_params->url = $link;
      return $this->showPage('redirect');
  }

  /**
   * @param $statuses
   * @return bool|void
   * @throws Exception
   */
  public function onPaymentNotification(&$statuses)
  {
    $app        = JFactory::getApplication();
    $jinput     = $app->input;
    $orderId = $jinput->get->get('order_id', '', 'STRING');

    if (empty($orderId)) {
      $msg = 'سفارش پیدا نشد.';
      $app->redirect(HIKASHOP_LIVE . 'index.php?option=com_hikashop', '<h4>' . $msg . '</h4>', 'Error');
    }

    $dbOrder = $this->getOrder($orderId);
    $this->loadPaymentParams($dbOrder);
    if (empty($this->payment_params)) {
      return false;
    }

    $this->loadOrderData($dbOrder);
    if (empty($dbOrder)) {
      echo 'Could not load any order for your notification ' . $orderId;
      $app->redirect(HIKASHOP_LIVE . 'index.php?option=com_hikashop', '<h4>' . $msg . '</h4>', 'Error');
    }

    $order_id = $dbOrder->order_id;
    $url = HIKASHOP_LIVE . 'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id=' . $order_id;
    $order_text = "\r\n" . JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE', $dbOrder->order_number, HIKASHOP_LIVE);
    $order_text .= "\r\n" . str_replace('<br/>', "\r\n", JText::sprintf('ACCESS_ORDER_WITH_LINK', $url));

    if ( !empty($orderId) && $orderId == $order_id) {
        $history = new stdClass();
        $history->notified = 0;
        $history->amount = round($dbOrder->order_full_price, (int)$this->currency->currency_locale['int_frac_digits']);
        $history->data = ob_get_clean();
        $data = array('order_id' => $order_id);
        $url = 'https://api.raypay.ir/raypay/api/v1/Payment/verify';
		$options = array('Content-Type: application/json');
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($_POST));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HTTPHEADER,$options );
		$result = curl_exec($ch);
		$result = json_decode($result );
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
        //$options = $this->options();
        //$result = $this->http->post($url, json_encode($data, true), $options);
        //$result = json_decode($result->body);
        //$http_status = $result->StatusCode;

        //check http error
        if ($http_status != 200) {
          $order_status = $this->payment_params->invalid_status;
          $email = new stdClass();
          $email->subject = JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER', 'raypay') . 'invalid transaction';
          $email->body = JText::sprintf("Hello,\r\n A raypay notification was refused because it could not be verified by the raypay server (or pay cenceled)") . "\r\n\r\n" . JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL . 'payment-raypay-error#invalidtnx');
          $msg = sprintf('خطا هنگام بررسی تراکنش. کد خطا: %s - پیام خطا: %s', $http_status, $result->Message);
          $this->modifyOrder($order_id, $order_status, NULL, $email);
          $this->order_log($order_id, $msg);
          $app->redirect(HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order', $msg, 'Error');
        }

        $state           = $result->Data->Status;
        $verify_order_id = $result->Data->FactorNumber;
        $verify_amount   = $result->Data->Amount;
        $verify_invoice_id   = $result->Data->InvoiceID;

        if ($state === 1)
        {
            $verify_status = 'پرداخت موفق';
        }
        else
        {
            $verify_status = 'پرداخت ناموفق';
        }

        $redirect_message_type = '';
        if (empty($verify_order_id) || empty($verify_amount) || $state !== 1) {
          $msg  = 'پرداخت ناموفق بوده است. شناسه ارجاع بانکی رای پی : ' . $verify_invoice_id;
          $order_status = $this->payment_params->pending_status;
          $order_text = JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL . 'payment-raypay-error#verify') . "\r\n\r\n" . $order_text;
          $redirect_message_type = 'Error';
        } else {
          $order_status = $this->payment_params->verified_status;
            $msg  = 'پرداخت شما با موفقیت انجام شد.';
        }

        $config = &hikashop_config();
        if ($config->get('order_confirmed_status', 'confirmed') == $order_status) {
          $history->notified = 1;
        }

        //generate msg for save to db
        $msgForLog = $verify_status . "شناسه ارجاع بانکی رای پی :  $verify_invoice_id ";

        $email = new stdClass();
        $email->subject = JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', 'raypay', $order_status, $dbOrder->order_number);
        $email->body = str_replace('<br/>', "\r\n", JText::sprintf('PAYMENT_NOTIFICATION_STATUS', 'raypay', $order_status)) . ' ' . JText::sprintf('ORDER_STATUS_CHANGED', $order_status) . "\r\n\r\n" . $order_text;
        $this->modifyOrder($order_id, $order_status, $history, $email);
        $this->order_log($order_id, $msgForLog);
        $app->redirect(HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order', '<h4>' . $msg . '</h4>', $redirect_message_type);
    }
    else {
      $msg = 'خطا هنگام بازگشت از درگاه رخ داده است';
      $order_status = $this->payment_params->invalid_status;
      $email = new stdClass();
      $email->subject = JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER', 'raypay') . 'invalid transaction';
      $email->body = JText::sprintf("Hello,\r\n A Raypay notification was refused because it could not be verified by the raypay server (or pay cenceled)") . "\r\n\r\n" . JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL . 'payment-raypay-error#invalidtnx');
      $action = false;
      $this->modifyOrder($order_id, $order_status, null, $email);
      $this->order_log($order_id, $msg);
      $app->redirect(HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order', '<h4>' . $msg . '</h4>', 'Error');
    }

  }


  public function onPaymentConfiguration(&$element)
  {
    $subtask = JRequest::getCmd('subtask', '');
    parent::onPaymentConfiguration($element);
  }

  /**
   * @param $element
   * @return bool
   */
  public function onPaymentConfigurationSave(&$element)
  {
    return true;
  }

  /**
   * @param $element
   */
  public function getPaymentDefaultValues(&$element)
  {
    $element->payment_name = 'پرداخت با رای پی';
    $element->payment_description = 'پرداخت امن به وسیله کلیه کارتهای عضو شتاب با درگاه پرداخت رای پی';
    $element->payment_images = '';
    $element->payment_params->invalid_status = 'cancelled';
    $element->payment_params->pending_status = 'created';
    $element->payment_params->verified_status = 'confirmed';
  }

  /**
   * @param $order_id
   * @param $msg
   * @param null $notified
   */
  public function order_log($order_id, $msg, $notified = null)
  {
    $order = new stdClass();
    $order->order_id = $order_id;
    $order->history = new stdClass();
    $order->history->history_reason = $msg;
    $order->history->history_payment_method = $this->name;
    $order->history->history_type = 'payment';
    $orderClass = hikashop_get('class.order');
    $orderClass->save($order);
  }

  /**
   * @param $amount
   * @param $currency
   * @return float|int
   */
  public function get_amount( $amount, $currency )
  {
    switch (strtolower($currency)) {
      case strtolower('IRR'):
      case strtolower('RIAL'):
        return $amount;

      case strtolower('IRT'):
      case strtolower('Iranian_TOMAN'):
      case strtolower('Iran_TOMAN'):
      case strtolower('Iranian-TOMAN'):
      case strtolower('Iran-TOMAN'):
      case strtolower('TOMAN'):
      case strtolower('Iran TOMAN'):
      case strtolower('Iranian TOMAN'):
      case strtolower('TOM'):
        return $amount * 10;

      default:
        return 0;
    }
  }

}
