<?php

namespace Jigoshop\Extension\PayflowGateway;

use Jigoshop\Integration;
use Jigoshop\Container;

class Common
{
	public function __construct()
	{
		Integration::addPsr4Autoload(__NAMESPACE__ . '\\', __DIR__);
		Integration\Helper\Render::addLocation('payflow_payment_gateway', JIGOSHOP_PAYFLOW_GATEWAY_DIR);
		/**@var Container $di*/
		$di = Integration::getService('di');
		$di->services->setDetails('jigoshop.payment.payflow_payment_gateway', __NAMESPACE__ . '\\Common\\Method', array(
			'jigoshop.options',
			'jigoshop.service.cart',
			'jigoshop.service.order',
			'jigoshop.messages',
		));
		$di->triggers->add('jigoshop.service.payment', 'addMethod', array('jigoshop.payment.payflow_payment_gateway'));
	}
}
new Common();