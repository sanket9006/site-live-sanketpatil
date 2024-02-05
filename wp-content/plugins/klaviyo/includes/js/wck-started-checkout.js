/**
 * WCK Started Checkout
 *
 * Incoming event object
 * @typedef {object} kl_checkout
 *   @property {string} email - Email of current logged in user
 *
 *   @property {object} event_data - Data for started checkout event
 *     @property {object} $extra - Event data
 *     @property {string} $service - Value will always be "woocommerce"
 *     @property {int} value - Total value of checkout event
 *     @property {array} Categories - Product categories (array of strings)
 *     @property {string} Currency - Currency type
 *     @property {string} CurrencySymbol - Currency type symbol
 *     @property {array} ItemNames - List of items in the cart
 *
 */


/**
 * Attach event listeners to save billing fields.
 */

var identify_object = {
  'company_id': public_key.token,
  'properties': {}
};

var klaviyo_cookie_id = '__kla_id';

function buildProfileRequestPayload(event_attributes) {
  return JSON.stringify({
    data: {
      type: "profile",
      attributes: event_attributes
    }
  })
}

function buildEventRequestPayload(customer_properties, event_properties, metric_attributes) {
  return JSON.stringify({
    data: {
      type: 'event',
      attributes: {
        properties: {
          ...event_properties,
        },
        metric: {
        data: {
          type: 'metric',
          attributes: {
            ...metric_attributes,
          }
        }
      },
      profile: {
        data: {
          type: 'profile',
          attributes: {
            ...customer_properties,
          }
        }
      }
      }
    }
  })
}

function makePublicAPIcall(endpoint, event_data) {
  var company_id = public_key.token;
  jQuery.ajax('https://a.klaviyo.com/' + endpoint + '?company_id=' + company_id, {
    type: "POST",
    contentType: "application/json",
    data: event_data,
    headers: {
      'revision': '2023-08-15',
      'X-Klaviyo-User-Agent': plugin_meta_data.data,
    }
  });
}

function getKlaviyoCookie() {
  var name = klaviyo_cookie_id + "=";
  var decodedCookie = decodeURIComponent(document.cookie);
  var ca = decodedCookie.split(';');
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) == ' ') {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      return atob(c.substring(name.length, c.length));
    }
  }
  return "";
}

function setKlaviyoCookie(cookie_data) {
  cvalue = btoa(JSON.stringify(cookie_data));
  var date = new Date();
  date.setTime(date.getTime() + (63072e6)); // adding 2 years in milliseconds to current time
  var expires = "expires=" + date.toUTCString();
  document.cookie = klaviyo_cookie_id + "=" + cvalue + ";" + expires + "; path=/";
}

  /**
   * Determines which block type is currently displayed and returns the type name as a string
   * @return {String}  'shipping' or 'billing'
   */
function getBlockNodeType() {
  var shippingFirstNameNode = jQuery('input[id="shipping-first_name"]');

  if (shippingFirstNameNode.length > 0) {
    return 'shipping';
  } else {
    return 'billing';
  }
}

  /**
   * Queries the dom for first_name, last_name, and email inputs being displayed on the checkout page
   * @return {object} an object of dom nodes (firstNameNode, lastNameNode, emailNode)
   */
function getInputNodeByDisplayType() {
  var shortCodeInputTypeIdentifiers = {
    firstName: 'billing_first_name',
    lastName: 'billing_last_name',
    email: 'billing_email',
  }
  var blockInputTypeIdentifier = {
    firstName: `${getBlockNodeType()}-first_name`,
    lastName: `${getBlockNodeType()}-last_name`,
    email: 'email'
  }

  var blockTypeFirstNameInput = jQuery(`input[id=${blockInputTypeIdentifier.firstName}]`)
  var blockTypeLastNameInput = jQuery(`input[id=${blockInputTypeIdentifier.lastName}]`)
  var blockTypeEmailInput = jQuery(`input[id=${blockInputTypeIdentifier.email}]`)
  var shortcodeFirstNameInput = jQuery(`input[name=${shortCodeInputTypeIdentifiers.firstName}]`)
  var shortcodeLastNameInput = jQuery(`input[name=${shortCodeInputTypeIdentifiers.lastName}]`)
  var shortcodeEmailInput = jQuery(`input[id=${shortCodeInputTypeIdentifiers.email}]`)
  var isShortcode = shortcodeFirstNameInput.length > 0

  if (isShortcode) {
    return {
      firstNameNode: shortcodeFirstNameInput,
      lastNameNode: shortcodeLastNameInput,
      emailNode: shortcodeEmailInput,
    }
  } else {
    return {
      firstNameNode: blockTypeFirstNameInput,
      lastNameNode: blockTypeLastNameInput,
      emailNode: blockTypeEmailInput,
    }
  }
}

function klIdentifyBillingField() {
  var billingFields = ["first_name", "last_name"];
  for (var i = 0; i < billingFields.length; i++) {
    (function () {
      var nameType = billingFields[i]
      var { emailNode } = getInputNodeByDisplayType()
      var shortCodeInputType = jQuery(`input[name="billing_${nameType}"]`)
      var blockInputType = jQuery(`input[id="${getBlockNodeType()}-${nameType}"]`)
      var inputNode = shortCodeInputType.length > 0 ? shortCodeInputType : blockInputType
      inputNode.change(function () {
        var email = emailNode.val()
        if (email) {
          identify_properties = {
            'email': email,
            [nameType]: jQuery.trim(jQuery(this).val())
          };
          setKlaviyoCookie(identify_properties);
          identify_object.properties = identify_properties;
          makePublicAPIcall('client/profiles/', buildProfileRequestPayload(identify_object));
        }
      })
    })();
  }
}

window.addEventListener("load", function () {
  // Custom checkouts/payment platforms may still load this file but won't
  // fire woocommerce_after_checkout_form hook to load checkout data.
  if (typeof kl_checkout === 'undefined') {
    return;
  }

  var WCK = WCK || {};
  WCK.trackStartedCheckout = function () {
    var metric_attributes = {
      'name': 'Started Checkout',
      'service': 'woocommerce'
    }
    var customer_properties = {}
    if (kl_checkout.email) {
      customer_properties['email'] = kl_checkout.email;
    } else if (kl_checkout.exchange_id) {
      customer_properties['_kx'] = kl_checkout.exchange_id;
    } else {
      return;
    }

    makePublicAPIcall('client/events/', buildEventRequestPayload(customer_properties, kl_checkout.event_data, metric_attributes));
  };

  var klCookie = getKlaviyoCookie();

  // Priority of emails for syncing Started Checkout event: Logged-in user,
  // cookied exchange ID, cookied email, billing email address
  if (kl_checkout.email !== "") {
    identify_object.properties = {
      'email': kl_checkout.email
    };
    makePublicAPIcall('client/profiles/', buildProfileRequestPayload(identify_object));
    setKlaviyoCookie(identify_object.properties);
    WCK.trackStartedCheckout();
  } else if (klCookie && JSON.parse(klCookie).exchange_id !== undefined) {
    kl_checkout.exchange_id = JSON.parse(klCookie).exchange_id;
    WCK.trackStartedCheckout();
  } else if (klCookie && JSON.parse(klCookie).email !== undefined) {
    kl_checkout.email = JSON.parse(klCookie).email;
    WCK.trackStartedCheckout();
  } else {
    if (jQuery) {
      var { firstNameNode, lastNameNode, emailNode } = getInputNodeByDisplayType()
      emailNode.change(function () {
        var elem = jQuery(this),
          email = jQuery.trim(elem.val());

        if (email && /@/.test(email)) {
          var params = {
            "email": email
          };

          if (firstNameNode.length > 0) {
            params["first_name"] = firstNameNode.val();
          }
          if (lastNameNode.length > 0) {
            params["last_name"] = lastNameNode.val();
          }

          setKlaviyoCookie(params);
          kl_checkout.email = params.email;
          identify_object.properties = params;
          makePublicAPIcall('client/profiles/', buildProfileRequestPayload((identify_object)));
          WCK.trackStartedCheckout();
        }
      });

      // Save billing fields
      klIdentifyBillingField();
    }
  }
});
