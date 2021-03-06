<?php
/**
 * GetGuestOrder for Modmore's Commerce
 * 
 * Made by Tony Klapatch with help from Mark Hamstra
 * https://github.com/poconosewandvac/Commerce_GuestOrder
 */

// Prevent cache misses because of form POST
header('Cache-Control: no cache');

// Get form values
$orderId = (int) $modx->getOption('order', $_REQUEST, 0);
$values = $modx->getOption('values', $_REQUEST, []);
$secret = $modx->getOption('secret', $_REQUEST, '');

// Enable direct access with order ID and secret from comOrder
$useSecret = (bool) $modx->getOption('useSecret', $scriptProperties, true);
// Let registered users check their order without signing in
$allowRegistered = $modx->getOption('allowRegistered', $scriptProperties, false);
// Fall back to check id column (for legacy installs pre-ref)
$useIdFallback = (bool) $modx->getOption('useIdFallback', $scriptProperties, true);

// Comma seperated list of comOrder fields to validate against
$fields = array_map('trim', explode(",", $modx->getOption("fields", $scriptProperties, "zip")));
// Address types to use. shipping, billing, or both (default)
$addressType = $modx->getOption('addressType', $scriptProperties, "both");

// Template settings
// Twig
$tpl = $modx->getOption('tpl', $scriptProperties, 'frontend/account/order-detail.twig');
// Chunks
$formTpl = $modx->getOption('formTpl', $scriptProperties, 'GetGuestOrderForm');
$errorTpl = $modx->getOption('errorTpl', $scriptProperties, 'GetGuestOrderError');

// Loading
$loadItems = (bool)$modx->getOption('loadItems', $scriptProperties, true);
$loadStatus = (bool)$modx->getOption('loadStatus', $scriptProperties, true);
$loadTransactions = (bool)$modx->getOption('loadTransactions', $scriptProperties, true);
$loadBillingAddress = (bool)$modx->getOption('loadBillingAddress', $scriptProperties, true);
$loadShippingAddress = (bool)$modx->getOption('loadShippingAddress', $scriptProperties, true);
$loadShipments = (bool)$modx->getOption('loadShipments', $scriptProperties, true);

// Lexicons to load
$modx->lexicon->load('commerce:frontend', 'commerce:default', 'commerce_guestorder:default');

if ($orderId <= 0) {
    // The form chunk to display when an order is not set
    return $modx->getChunk($formTpl);
}

// Initialize Commerce
$path = $modx->getOption('commerce.core_path', null, MODX_CORE_PATH . 'components/commerce/') . 'model/commerce/';
$params = ['mode' => $modx->getOption('commerce.mode')];
$commerce = $modx->getService('commerce', 'Commerce', $path, $params);
    
if (!($commerce instanceof Commerce)) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Could not load Commerce service in GetGuestOrder snippet.');
    return 'Could not load Commerce. Please try again later.';
}
    
if ($commerce->isDisabled()) {
    return $commerce->adapter->lexicon('commerce.mode.disabled.message');
}

// Allowed order classes to use in the orderQuery
$allowedClasses = ['comProcessingOrder', 'comCompletedOrder', 'comCancelledOrder'];
foreach ($allowedClasses as $ac) {
    $allowedClasses = array_merge($allowedClasses, $modx->getDescendants($ac));
}
$allowedClasses = array_unique($allowedClasses);

// Build the order query
$queryFields = [
    'test' => $commerce->isTestMode(),
    'class_key:IN' => $allowedClasses,
];

if (!$allowRegistered) {
    $queryFields['user'] = 0;
}

if ($useSecret && $secret) {
    $queryFields['secret'] = $secret;
}

// Check for order by reference first
$orderRefQuery = $modx->newQuery('comOrder');
$orderRefQuery->where(array_merge($queryFields, ['reference_incr' => $orderId]));
$order = $modx->getObject('comOrder', $orderRefQuery);

// Attempt to fetch order by ID (legacy) if it cannot be found by reference
if (!$order && $useIdFallback) {
    $orderIdQuery = $modx->newQuery('comOrder');
    $orderIdQuery->where(array_merge($queryFields, ['id' => $orderId]));
    $order = $modx->getObject('comOrder', $orderIdQuery);
}
    
// Check if the order exists.
if (!$order) {
    return $modx->getChunk($errorTpl, ['order' => $order, 'error' => $modx->lexicon('commerce_guestorder.error_order_dne')]);
}

// Address validation if the secret is not being used.
if (!$secret) {
    $orderAddressQuery = $modx->newQuery('comOrderAddress');
    $orderAddressQuery->where([
        'order' => $order->get('id'),
    ]);
    if ($addressType !== "both") {
        $orderAddressQuery->where([
            'type' => $addressType    
        ]);
    }
    $orderAddresses = $modx->getCollection("comOrderAddress", $orderAddressQuery);

    if (!$orderAddresses) {
        return $modx->getChunk($errorTpl, ['order' => $order, 'error' => $modx->lexicon('commerce_guestorder.error_order_no_addr')]);
    }

    switch ($addressType) {
        case "shipping":
            $address = $order->getShippingAddress();
            
            foreach ($fields as $field) {
                if ($address->get($field) !== $values[$field]) {
                    return $modx->getChunk($errorTpl, ['order' => $order, 'error' => $modx->lexicon('commerce_guestorder.error_ship_addr')]);
                }
            }
            
            break;
        case "billing":
            $address = $order->getBillingAddress();
            
            foreach ($fields as $field) {
                if ($address->get($field) !== $values[$field]) {
                    return $modx->getChunk($errorTpl, ['order' => $order, 'error' => $modx->lexicon('commerce_guestorder.error_bill_addr')]);
                }
            }
            
            break;
        case "both":
            $shippingAddress = $order->getShippingAddress();
            $billingAddress = $order->getBillingAddress();

            foreach ($fields as $field) {
                if (!(($shippingAddress->get($field) === $values[$field]) || ($billingAddress->get($field) === $values[$field]))) {
                    return $modx->getChunk($errorTpl, ['order' => $order, 'error' => $modx->lexicon('commerce_guestorder.error_both_addr')]);
                }
            }
            
            break;
        default:
            // In case addressType is not set to shipping, billing, or both
            return $modx->getChunk($errorTpl, ['order' => $order, 'error' => $modx->lexicon('commerce_guestorder.error_type_addr')]);
    };
}
    
// Grab the data and output into twig
$data = [];
$data['order'] = $order->toArray();
$data['state'] = $order->getState();
    
if ($loadItems) {
    $items = [];
    foreach ($order->getItems() as $item) {
        $ta = $item->toArray();
        if ($product = $item->getProduct()) {
            $ta['product'] = $product->toArray();
        }
        $items[] = $ta;
    }
    $data['items'] = $items;
}
if ($loadStatus) {
    $status = $order->getStatus();
    $data['status'] = $status->toArray();
}

if ($loadTransactions) {
    $trans = [];
    $transactions = $order->getTransactions();
    foreach ($transactions as $transaction) {
        if ($transaction->isCompleted()) {
            $traa = $transaction->toArray();
            if ($method = $transaction->getMethod()) {
                $traa['method'] = $method->toArray();
            }
            $trans[] = $traa;
        }
    }
    $data['transactions'] = $trans;
}
    
if ($loadBillingAddress) {
    $ba = $order->getBillingAddress();
    $data['billing_address'] = $ba->toArray();
}
if ($loadShippingAddress) {
    $sa = $order->getShippingAddress();
    $data['shipping_address'] = $sa->toArray();
}

if ($loadShipments) {
    $gs = $order->getShipments();
    
    foreach ((array) $gs as $s) {
        $shipments[] = $s->toArray();
    }
    
    $data['shipments'] = $shipments;
}

if ($tpl !== '') {
    try {
        $output = $commerce->twig->render($tpl, $data);
    }
    catch(Exception $e) {
        $modx->log(modX::LOG_LEVEL_ERROR, 'Error processing GetGuestOrder snippet on resource #' . $modx->resource->get('id') . ' - twig threw exception ' . get_class($e) . ': ' . $e->getMessage());
        return 'Sorry, could not show your order.';
    }
} else {
    $output = '<pre>' . print_r($data, true) . '</pre>';
}
    
return $output;