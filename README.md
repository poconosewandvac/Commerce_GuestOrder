# Commerce_GuestOrder

Lets guest customer's easily view their previous orders. Uses the frontend/account/order-detai.twig file in your Commerce theme. Requires Modmore's Commerce to use https://www.modmore.com/commerce/

## Usage

After downloading and installing the transport package, create a Guest Order resource and call the snipped uncached.

```
[[!GetGuestOrder]]
```

This will use the included chunks "GetGuestOrderForm" for the formTpl option and "GetGuestOrderError" for the errorTpl option.

## Options

### Validation Fields

By default, the snippet validates against the zip field in comOrder, however it can validate any field in comOrder, or even multiple fields. For example, if you wanted to validate the zip and email field you would call GetGuestOrder like

```
[[!GetGuestOrder? &fields=`zip,email`]]
```

This would also require a change to your formTpl to input the new field, which can be any chunk you create. You will need to add the fields using the name attribute name="values[column]". For example, a sample form for using zip and email could be

```HTML
<form action="[[~[[*id]]]]" method="POST">
    <input type="text" name="order" placeholder="Order ID" />
    <input type="text" placeholder="Zip Code" name="values[zip]" />
    <input type="text" placeholder="Email" name="values[email]" />
    <button type="submit">Submit</button>
</form>
```

### Secret

Inside comOrder, there is a column, secret, that can be alternatively be used to access the order. This is useful for linking a "Track my order" button inside a status change. For example, inside "order-received.twig" you could have (where RESOURCEID is the page you have the GetGuestOrder snippet on)

```HTML
{% if order.user == 0 %}
    <h1><a href="[[~RESOURCEID? &scheme=`full`]]?order={{ order.id|url_encode }}&secret={{ order.secret|url_encode }}">Track your order</a></h1>
{% else %}
    <!-- Something could be put here for registered users to login to their account to track their order -->
{% endif %}
```

If you do not want this feature, you can disable it using the snippet option useSecret and set it to 0.

### Address Type

This option defines which address to verify the fields against. It can be either shipping, billing, or both (default).

### Template

Below are the template settings you can override.

- tpl: template to use for order view
- formTpl: template to use for the form
- errorTpl: template to use when order could not be verified or found
