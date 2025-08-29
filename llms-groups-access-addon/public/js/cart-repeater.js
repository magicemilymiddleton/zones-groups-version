jQuery(document).ready(function($) {

  // Add a new cart row
  $(document).on('click', '#llmsgaa-add-row', function(e) {
    e.preventDefault();

    const $tbody = $('#llmsgaa-cart-body');
    const rowCount = $tbody.find('tr.llmsgaa-cart-row').length;
    const $newRow = $tbody.find('tr.llmsgaa-cart-row').last().clone();

    $newRow.find('input').each(function() {
      const $input = $(this);
      const name = $input.attr('name');

      if (name) {
        const newName = name.replace(/\[items\]\[\d+\]/, `[items][${rowCount}]`);
        $input.attr('name', newName).val('');
      }
    });

    $tbody.append($newRow);
  });

  // Remove a cart row (but keep at least one)
  $(document).on('click', '.llmsgaa-remove-row', function(e) {
    e.preventDefault();

    const $tbody = $('#llmsgaa-cart-body');
    const $rows = $tbody.find('tr.llmsgaa-cart-row');

    if ($rows.length > 1) {
      $(this).closest('tr').remove();

      // Re-index all remaining rows
      $tbody.find('tr.llmsgaa-cart-row').each(function(index) {
        $(this).find('input').each(function() {
          const $input = $(this);
          const name = $input.attr('name');

          if (name) {
            const newName = name.replace(/\[items\]\[\d+\]/, `[items][${index}]`);
            $input.attr('name', newName);
          }
        });
      });
    }
  });

});
