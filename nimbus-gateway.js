const successCallback = function (data) {
  const checkout_form = $("form.woocommerce-checkout");

  console.log(data);

  checkout_form.off("checkout_place_order", tokenRequest);

  checkout_form.submit();
};

const errorCallback = function (data) {
  console.log(data);
};

const tokenRequest = function (data) {
  console.log(data);
  const secretKey = nimbus_params.secretKey;
  const email = document.getElementById("billing_email").value;

  var myHeaders = new Headers();
  myHeaders.append("Authorization", "Bearer " + secretKey);
  myHeaders.append("Content-Type", "application/json");

  var raw = JSON.stringify({
    client: {
      email: email,
    },
    products: [
      {
        title: "products",
        price: 10,
      },
    ],
    success_redirect: "http://staging.nimbusvapour.com.au",
    failure_redirect: "http://staging.nimbusvapour.com.au",
  });

  var requestOptions = {
    method: "POST",
    headers: myHeaders,
    body: raw,
    redirect: "follow",
  };

  fetch("https://gate.novattipayments.com/api/v0.6/orders/", requestOptions)
    .then((res) => res.json())
    .then((data) => console.log(data.id, data.direct_post))
    .catch((error) => console.log("error", error));
};

jQuery(function ($) {
  var checkout_form = $("form.woocommerce-checkout");
  checkout_form.on("checkout_place_order", tokenRequest);
});
