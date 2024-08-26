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

  var diagramData = ailAjax.savedDiagram || null;

  // Load saved diagram if it exists
  if (diagramData) {
    renderDiagram();
    $("#generate-diagram").text("Update Diagram");
    $("#delete-diagram, #download-diagram, #download-svg").show();
  }

  $("#generate-diagram").on("click", function (e) {
    e.preventDefault();
    var $button = $(this);
    var $diagram = $("#link-diagram");
    var buttonText = diagramData ? "Updating..." : "Generating...";

    $button.prop("disabled", true).text(buttonText);

    if (!diagramData) {
      $diagram.html(
        "<p>Generating diagram. This may take a few moments...</p>"
      );
    }

    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "generate_link_diagram",
      },
      success: function (response) {
        if (response.success) {
          diagramData = response.data;
          renderDiagram();
          $("#delete-diagram, #download-diagram, #download-svg").show();
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
        $button.prop("disabled", false).text("Update Diagram");
      },
    });
  });

  function renderDiagram() {
    var $diagram = $("#link-diagram");
    $diagram.empty();
    var diagramContainer = $('<div class="mermaid">').text(diagramData);
    $diagram.append(diagramContainer);
    try {
      mermaid.init(undefined, $(".mermaid"));
    } catch (error) {
      console.error("Mermaid rendering error:", error);
      console.log("Diagram data:", diagramData);
      $diagram.html(
        "<p>Failed to render diagram. The data might be too complex or contain invalid syntax. Please try again with fewer words or content items.</p>"
      );
      $diagram.append("<pre>" + diagramData + "</pre>");
    }
  }

  $("#delete-diagram").on("click", function (e) {
    e.preventDefault();
    var $button = $(this);
    $button.prop("disabled", true).text("Deleting...");

    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "delete_link_diagram",
      },
      success: function (response) {
        if (response.success) {
          var $diagram = $("#link-diagram");
          $diagram.empty();
          diagramData = null;
          $("#generate-diagram").text("Generate Diagram");
          $("#delete-diagram, #download-diagram, #download-svg").hide();
        } else {
          alert("Failed to delete the diagram. Please try again.");
        }
      },
      error: function () {
        alert("An error occurred while deleting the diagram. Please try again.");
      },
      complete: function () {
        $button.prop("disabled", false).text("Delete Diagram");
      },
    });
  });

  $("#download-diagram").on("click", function (e) {
    e.preventDefault();
    downloadDiagram("png");
  });

  $("#download-svg").on("click", function (e) {
    e.preventDefault();
    downloadDiagram("svg");
  });

  function downloadDiagram(format) {
    var $diagram = $("#link-diagram svg");
    if ($diagram.length) {
      var svgData = new XMLSerializer().serializeToString($diagram[0]);
      if (format === "svg") {
        var blob = new Blob([svgData], { type: "image/svg+xml;charset=utf-8" });
        saveAs(blob, "internal_link_diagram.svg");
      } else {
        var canvas = document.createElement("canvas");
        var ctx = canvas.getContext("2d");
        var img = new Image();
        img.onload = function () {
          canvas.width = img.width;
          canvas.height = img.height;
          ctx.drawImage(img, 0, 0);
          canvas.toBlob(function (blob) {
            saveAs(blob, "internal_link_diagram.png");
          });
        };
        img.src =
          "data:image/svg+xml;base64," +
          btoa(unescape(encodeURIComponent(svgData)));
      }
    } else {
      alert("No diagram to download. Please generate a diagram first.");
    }
  }

  // Blacklist management
  $("#add-blacklist-word").on("click", function (e) {
    e.preventDefault();
    var word = $("#new-blacklist-word").val().trim();
    if (word) {
      $.ajax({
        url: ailAjax.ajaxurl,
        type: "POST",
        data: {
          action: "add_blacklist_word",
          word: word,
        },
        success: function (response) {
          if (response.success) {
            $("#blacklist-manager ul").append(
              "<li>" +
                word +
                ' <button class="remove-blacklist-word" data-word="' +
                word +
                '">Remove</button></li>'
            );
            $("#new-blacklist-word").val("");
          } else {
            alert(
              "Failed to add word to blacklist. It may already be in the list."
            );
          }
        },
      });
    }
  });

  $(document).on("click", ".remove-blacklist-word", function (e) {
    e.preventDefault();
    var $button = $(this);
    var word = $button.data("word");
    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "remove_blacklist_word",
        word: word,
      },
      success: function (response) {
        if (response.success) {
          $button.parent().remove();
        } else {
          alert("Failed to remove word from blacklist.");
        }
      },
    });
  });
});
