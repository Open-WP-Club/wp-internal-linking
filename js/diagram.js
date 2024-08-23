jQuery(document).ready(function ($) {
  // Initialize mermaid with custom configuration
  mermaid.initialize({
    startOnLoad: true,
    theme: "default",
    flowchart: {
      curve: "basis",
      nodeSpacing: 50,
      rankSpacing: 100,
      useMaxWidth: false,
    },
    securityLevel: "loose",
  });

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
          try {
            mermaid.init(undefined, $(".mermaid"));
          } catch (error) {
            console.error("Mermaid rendering error:", error);
            console.log("Diagram data:", diagram);
            $diagram.html(
              "<p>Failed to render diagram. The data might be too complex or contain invalid syntax. Please try again with fewer words or content items.</p>"
            );
            $diagram.append("<pre>" + diagram + "</pre>");
          }
        } else {
          $diagram.html(
            "<p>Failed to generate diagram data. Please try again.</p>"
          );
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.error("AJAX error:", textStatus, errorThrown);
        $diagram.html(
          "<p>An error occurred while communicating with the server. Please try again.</p>"
        );
      },
      complete: function () {
        $button.prop("disabled", false).text("Generate Diagram");
      },
    });
  });
});
