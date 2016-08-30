var fraudprobability_timer = null;


jQuery(function ($) {

  $(document).ready(function () {

    $('body').append('<div id="adminhtml-fraudprobability-info" style="display: none;"><span class="arrow"></span><div class="content"></div></div>');

    var info = $('#adminhtml-fraudprobability-info');
    function showInfo(el)
    {
      var offset = $(el).offset();
      x = offset.left + 30;
      y = offset.top - info.height() / 2 + 7;
      info.css({"left": x + "px"});
      info.css({"top": y + "px"});

      var content = $(el).parent().find('.info');
      if (!content.length)
      {
        $(el).parent().append('<div class="info" style="display: none;"></div>');
      }
      content = $(el).parent().find('.info');

      if (!content.html())
      {
        content.html('<span class="wait"></span>');
        var url = $(el).attr('href');
        if (url)
        {
          $.get(
            url,
            function onAjaxSuccess(data)
            {
              if (data.indexOf('<!--#access-validate#-->') == -1)
              {
                data = "<div class='info-middle'>Error, Please update page</div>";
                content.html('');
              } else {
                content.html(data);
              }
              if ($(el).hasClass('over'))
              {
                info.find('.content').html(data);
              }
            }
          );
        }
      }
      info.find('.content').html(content.html());
      info.show();
    }



    $('div.adminhtml-fraudprobability a')
      .live('click', function(event) {
        event.preventDefault();
      }).live('mouseover', function(event) {
        clearInterval(fraudprobability_timer);
        info.hide();
        var row =  $(this).parents().eq(2);
        if (row.length)
        {
          var title = row.attr('title');
          row.attr('title', '');
          $(this).attr('rowtitle', title);
        }
        $(this).addClass('over');
        event.preventDefault();
        showInfo(this);
      }).live('mouseout', function(event) {
        $(this).removeClass('over');
        var title = $(this).attr('rowtitle');
        var row =  $(this).parents().eq(2);
        if (row.length && title)
        {
          row.attr('title', title);
        }
        fraudprobability_timer  = setInterval(function(){info.hide(); clearInterval(fraudprobability_timer);}, 500);
      });

    info.live('mouseover', function(event) {
      clearInterval(fraudprobability_timer);
    }).live('mouseout', function(event) {
        fraudprobability_timer  = setInterval(function(){info.hide(); clearInterval(fraudprobability_timer);}, 200);

    });
  });
});