/**
 * Chocante VAT EU
 * Checkout client validation
 */
(function ($) {
  const checkoutForm = $('form.checkout');
  const companyField = 'input[name="billing_company"]';
  const taxIdField = 'input[name="billing_tax_id"]'
  const REQUIRED_CLASS = 'validate-required';
  const INVALID_CLASS = 'woocommerce-invalid woocommerce-invalid-required-field';
  const VALIDATED_CLASS = 'woocommerce-validated';
  const PARENT_ROW = '.form-row';

  checkoutForm.on('change', `${companyField}, ${taxIdField}`, function () {
    const companyFieldHasValue = $(companyField).val().length > 0;
    const taxIdFieldHasValue = $(taxIdField).val().length > 0;
    const taxIdFieldParent = $(taxIdField).closest(PARENT_ROW);
    const companyFieldParent = $(companyField).closest(PARENT_ROW);

    if (companyFieldHasValue && !taxIdFieldHasValue) {
      taxIdFieldParent.removeClass(VALIDATED_CLASS).addClass(`${REQUIRED_CLASS} ${INVALID_CLASS}`);
    }

    if (!companyFieldHasValue && taxIdFieldHasValue) {
      companyFieldParent.removeClass(VALIDATED_CLASS).addClass(`${REQUIRED_CLASS} ${INVALID_CLASS}`);
    }

    if (!companyFieldHasValue && !taxIdFieldHasValue) {
      window.requestAnimationFrame(function () {
        companyFieldParent.removeClass(`${REQUIRED_CLASS} ${INVALID_CLASS} ${VALIDATED_CLASS}`);
        taxIdFieldParent.removeClass(`${REQUIRED_CLASS} ${INVALID_CLASS} ${VALIDATED_CLASS}`);
      });
    }
  });

  $(document.body).on('checkout_error', function (event, errorMessage) {
    if (errorMessage.includes('VAT')) {
      $(taxIdField).closest(PARENT_ROW).removeClass(VALIDATED_CLASS).addClass(INVALID_CLASS);
    }
  });
})(jQuery);