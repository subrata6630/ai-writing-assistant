jQuery(document).ready(function ($) {
  $('#aiwa-generate-btn').on('click', function (e) {
    e.preventDefault();

    const prompt = $('#aiwa-prompt').val();
    const type = $('#aiwa-type').val();
    const lang = $('#aiwa-language').val();

    if (!prompt) {
      alert('Please enter a prompt.');
      return;
    }

    $('#aiwa-output').html('<p><em>Generating content...</em></p>');
    $('#aiwa-actions').hide();

    $.ajax({
      method: 'POST',
      url: aiwa_obj.ajax_url,
      data: {
        action: 'aiwa_generate',
        nonce: aiwa_obj.nonce,
        prompt,
        type,
        lang
      },
      success: function (res) {
        if (res.success) {
          $('#aiwa-output').html(`<textarea id="aiwa-generated-content" rows="10" cols="80">${res.data}</textarea>`);
          $('#aiwa-actions').show();
        } else {
          $('#aiwa-output').html('<p><strong>Error:</strong> ' + res.data + '</p>');
        }
      },
      error: function () {
        $('#aiwa-output').html('<p><strong>Error:</strong> Failed to connect to server.</p>');
      }
    });
  });

  $('#aiwa-save-post').on('click', function () {
    const content = $('#aiwa-generated-content').val();
    const title = content.split('.')[0].substring(0, 60) || 'AI Generated Post';

    $.post(aiwa_obj.ajax_url, {
      action: 'aiwa_save_post',
      nonce: aiwa_obj.nonce,
      title,
      content
    }, function (res) {
      alert(res.success ? res.data : 'Failed to save post.');
    });
  });

  $('#aiwa-save-product').on('click', function () {
    const content = $('#aiwa-generated-content').val();
    const title = content.split('.')[0].substring(0, 60) || 'AI Product';

    $.post(aiwa_obj.ajax_url, {
      action: 'aiwa_save_product',
      nonce: aiwa_obj.nonce,
      title,
      content
    }, function (res) {
      alert(res.success ? res.data : 'Failed to save product.');
    });
  });
});

