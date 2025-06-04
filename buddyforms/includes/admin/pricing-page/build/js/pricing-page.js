(() => {
  // src/js/pricing-page.js
  var BuddyFormsPricingPage = class {
    constructor() {
      this.purchaseButtons = document.querySelectorAll("[data-purchase-licenses]");
      this.productId = "2046";
      this.planId = "4316";
      this.publicKey = "pk_ee958df753d34648b465568a836aa";
      this.purchaseHandler = null;
      this.init();
    }
    init() {
      this.purchaseHandler = new FS.Checkout({
        product_id: this.productId,
        plan_id: this.planId,
        public_key: this.publicKey
      });
      this.purchaseButtons.forEach((button) => {
        button.addEventListener("click", (e) => {
          this.purchasePlanHandler(e);
        });
      });
    }
    purchasePlanHandler(e) {
      const licenses = e.target.dataset.purchaseLicenses;
      this.purchaseHandler.open({
        licenses
      });
    }
  };
  document.addEventListener("DOMContentLoaded", function() {
    bfPricingPage = new BuddyFormsPricingPage();
  });
  jQuery(document).ready(function(jQuery2) {
    jQuery2("#purchase").on("click", function(e) {
      var handler = FS.Checkout.configure({
        plugin_id: "391",
        plan_id: "583",
        public_key: "pk_d462eaeb50bc258e3d97c2c146eb6"
      });
      handler.open({
        name: "ThemeKraft Bundle",
        licenses: jQuery2("#licenses-1").val(),
        purchaseCompleted: function(response) {
        },
        success: function(response) {
        }
      });
      e.preventDefault();
    });
    jQuery2("#purchase-2").on("click", function(e) {
      var handler = FS.Checkout.configure({
        plugin_id: "7487",
        plan_id: "12239",
        public_key: "pk_68d9aeacd7352d37de451d91e3081"
      });
      handler.open({
        name: "ThemeKraft Bundle",
        licenses: jQuery2("#licenses-2").val(),
        purchaseCompleted: function(response) {
        },
        success: function(response) {
        }
      });
      e.preventDefault();
    });
    jQuery2("#purchase-3").on("click", function(e) {
      var handler = FS.Checkout.configure({
        plugin_id: "2046",
        plan_id: "4316",
        public_key: "pk_ee958df753d34648b465568a836aa"
      });
      handler.open({
        name: "ThemeKraft Bundle",
        licenses: jQuery2("#licenses-3").val(),
        purchaseCompleted: function(response) {
        },
        success: function(response) {
        }
      });
      e.preventDefault();
    });
    jQuery2("select#licenses-1").change(function() {
      var selectedCountry = jQuery2(this).children("option:selected").val();
      if (selectedCountry == "1") {
        jQuery2(".fs-bundle-price-1").text("39.99");
        jQuery2("#savings-price").text("59.99");
      }
      if (selectedCountry == "5") {
        jQuery2(".fs-bundle-price-1").text("69.99");
        jQuery2("#savings-price").text("199.95");
      }
      if (selectedCountry == "unlimited") {
        jQuery2(".fs-bundle-price-1").text("79.99");
        jQuery2("#savings-price").text("219.99");
      }
    });
    jQuery2("select#licenses-2").change(function() {
      var selectedCountry = jQuery2(this).children("option:selected").val();
      if (selectedCountry == "1") {
        jQuery2(".fs-bundle-price-2").text("89.99");
        jQuery2("#savings-price-2").text("342.84");
      }
      if (selectedCountry == "5") {
        jQuery2(".fs-bundle-price-2").text("99.99");
        jQuery2("#savings-price-2").text("525.84");
      }
      if (selectedCountry == "unlimited") {
        jQuery2(".fs-bundle-price-2").text("119.99");
        jQuery2("#savings-price-2").text("688.84");
      }
    });
    jQuery2("select#licenses-3").change(function() {
      var selectedCountry = jQuery2(this).children("option:selected").val();
      if (selectedCountry == "1") {
        jQuery2(".fs-bundle-price-3").text("99.99");
        jQuery2("#savings-price-3").text("$2,799.72");
      }
      if (selectedCountry == "5") {
        jQuery2(".fs-bundle-price-3").text("149.99");
        jQuery2("#savings-price-3").text("$13,998.60");
      }
      if (selectedCountry == "unlimited") {
        jQuery2(".fs-bundle-price-3").text("249.99");
        jQuery2("#savings-price-3").text("$6,999.72");
      }
    });
    jQuery2(".bundle-list-see-more").click(function() {
      jQuery2(".list-bundle-ul").animate({
        height: "1200"
      });
      jQuery2(".separator, .bundle-list-see-more").hide();
    });
  });
})();
