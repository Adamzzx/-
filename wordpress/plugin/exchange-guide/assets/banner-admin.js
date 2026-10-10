jQuery(function ($) {
  $('#cg-banner-select').on('click', function () {
    const frame = wp.media({ title: '选择有权使用的注册横幅', multiple: false, library: { type: 'image' } });
    frame.on('select', function () {
      const image = frame.state().get('selection').first().toJSON();
      $('#cg_banner_attachment').val(image.id);
      $('#cg-banner-preview').attr('src', image.url).show();
    });
    frame.open();
  });
  $('#cg-banner-clear').on('click', function () {
    $('#cg_banner_attachment').val(''); $('#cg-banner-preview').hide();
  });
});
