   config.addRss = function() {
      var e = document.createElement('link');
      e.id = "rss";
      e.rel = "alternate";
      e.type = "application/rss+xml";
      e.title = "Rss feed";
      e.href = "./rss.xml";
      var s = document.getElementsByTagName('script')[0];
      s.parentNode.appendChild(e);
   };
   config.addRss();

