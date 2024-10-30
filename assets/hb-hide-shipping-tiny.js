jQuery(document).ready(function($){
    $(document).on("click",".wc-shipping-zone-method-rows",function() {
        $('.wc-enhanced-select').select2();
        $('.wc-enhanced-select').selectWoo({
            minimumResultsForSearch: 2,
        });   
    });
});

