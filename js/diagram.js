jQuery(document).ready(function ($) {
  // Initialize mermaid
  mermaid.initialize({ startOnLoad: true, theme: "default" });

  $("#generate-diagram").on("click", function (e) {
    e.preventDefault();
    var $button = $(this);
    var $diagram = $("#link-diagram");

    $button.prop("disabled", true).text("Generating...");
    $diagram.html("<p>Generating diagram. This may take a few moments...</p>");

    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "generate_link_diagram",
      },
      success: function (response) {
        if (response.success) {
          var diagram = response.data;
          // Clear previous diagram
          $diagram.empty();
          // Create a new div for the diagram
          var diagramContainer = $('<div class="mermaid">').text(diagram);
          $diagram.append(diagramContainer);
          // Render the new diagram
          mermaid.init(undefined, $(".mermaid"));
        } else {
          $diagram.html("<p>Failed to generate diagram. Please try again.</p>");
        }
      },
      error: function () {
        $diagram.html("<p>An error occurred. Please try again.</p>");
      },
      complete: function () {
        $button.prop("disabled", false).text("Generate Diagram");
      },
    });
  });
});
