(function($){
  function recalc($row){
    var $form   = $row.closest('form.variants-form, #create-group-box form');
    var percent = parseInt($form.data('percent') || 20, 10);
    var rate    = parseFloat($form.data('rate') || 1);
    var price   = parseFloat($row.find('input.price, input[name$="[price]"]').val() || 0);
    var rent    = Math.round(price * percent / 100);
    var depo    = Math.round(price * rate);
    $row.find('.rent').text(rent);
    $row.find('.deposit').text(depo);
  }

  // перерахунок при зміні ціни в будь-якій таблиці
  $(document).on('input', '.variants-table input.price, .variants-table input[name$="[price]"]', function(){
    recalc($(this).closest('tr'));
  });

  // додати рядок в ІСНУЮЧУ групу
  $(document).on('click', '[data-add-row]', function(e){
    e.preventDefault();
    var groupId = $(this).data('group');
    var $tbody  = $('#rows-' + groupId);
    var idx     = $tbody.children('tr').length;

    $(this).request('onAddRow', {
      data: { groupId: groupId, idx: idx },
      success: function(data){
        if (data.result) {
          $tbody.append(data.result);
          recalc($tbody.children('tr').last());
        }
      }
    });
    return false;
  });

  // додати рядок у форму НОВОЇ групи
  $(document).on('click', '[data-add-new-row]', function(e){
    e.preventDefault();
    var $tbody = $('#new-rows');
    var idx    = $tbody.children('tr').length;

    $(this).request('onAddNewRow', {
      data: { idx: idx },
      success: function(data){
        if (data.result) {
          $tbody.append(data.result);
          recalc($tbody.children('tr').last());
        }
      }
    });
    return false;
  });

  // після будь-якого ajax-оновлення — перерахунок видимих рядків
  $(document).on('ajaxUpdateComplete', function(){
    $('.vrow').each(function(){ recalc($(this)); });
  });
})(jQuery);
