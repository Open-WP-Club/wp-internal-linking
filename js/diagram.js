jQuery(document).ready(function ($) {
  mermaid.initialize({ startOnLoad: false });

  $("#generate-diagram").on("click", function (e) {
    e.preventDefault();
    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "generate_link_diagram",
      },
      success: function (response) {
        if (response.success) {
          var diagram = response.data;
          var insertSvg = function (svgCode, bindFunctions) {
            $("#link-diagram").html(svgCode);
          };
          mermaid.render("mermaid-diagram", diagram, insertSvg);
        } else {
          alert("Failed to generate diagram");
        }
      },
      error: function () {
        alert("An error occurred. Please try again.");
      },
    });
  });
});
