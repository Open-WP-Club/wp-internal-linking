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

  var contentRelationshipMapData = ailAjax.savedDiagram || null;
  var internalLinkAnalysisData = ailAjax.savedInternalLinkAnalysis || null;

  // Load saved content relationship map if it exists
  if (contentRelationshipMapData) {
    renderContentRelationshipMap();
    $("#generate-diagram").text("Update Map");
    $("#delete-diagram, #download-diagram, #download-svg").show();
  }

  // Load saved internal link analysis if it exists
  if (internalLinkAnalysisData) {
    initializeInternalLinkAnalysis();
  }

  $("#generate-diagram").on("click", function (e) {
    e.preventDefault();
    var $button = $(this);
    var $diagram = $("#content-relationship-map");
    var buttonText = contentRelationshipMapData
      ? "Updating..."
      : "Generating...";

    $button.prop("disabled", true).text(buttonText);

    if (!contentRelationshipMapData) {
      $diagram.html("<p>Generating map. This may take a few moments...</p>");
    }

    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "generate_content_relationship_map",
      },
      success: function (response) {
        if (response.success) {
          contentRelationshipMapData = response.data;
          renderContentRelationshipMap();
          $("#delete-diagram, #download-diagram, #download-svg").show();
        } else {
          $diagram.html(
            "<p>Failed to generate map data. Please try again.</p>"
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
        $button.prop("disabled", false).text("Update Map");
      },
    });
  });

  function renderContentRelationshipMap() {
    var $diagram = $("#content-relationship-map");
    $diagram.empty();
    var diagramContainer = $('<div class="mermaid">').text(
      contentRelationshipMapData
    );
    $diagram.append(diagramContainer);
    try {
      mermaid.init(undefined, $(".mermaid"));
    } catch (error) {
      console.error("Mermaid rendering error:", error);
      console.log("Diagram data:", contentRelationshipMapData);
      $diagram.html(
        "<p>Failed to render map. The data might be too complex or contain invalid syntax. Please try again with fewer words or content items.</p>"
      );
      $diagram.append("<pre>" + contentRelationshipMapData + "</pre>");
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
        action: "delete_content_relationship_map",
      },
      success: function (response) {
        if (response.success) {
          var $diagram = $("#content-relationship-map");
          $diagram.empty();
          contentRelationshipMapData = null;
          $("#generate-diagram").text("Generate Map");
          $("#delete-diagram, #download-diagram, #download-svg").hide();
        } else {
          alert("Failed to delete the map. Please try again.");
        }
      },
      error: function () {
        alert("An error occurred while deleting the map. Please try again.");
      },
      complete: function () {
        $button.prop("disabled", false).text("Delete Map");
      },
    });
  });

  $("#download-diagram").on("click", function (e) {
    e.preventDefault();
    downloadDiagram("png", "content-relationship-map");
  });

  $("#download-svg").on("click", function (e) {
    e.preventDefault();
    downloadDiagram("svg", "content-relationship-map");
  });

  // Internal Link Analysis
  $("#analyze-internal-links").on("click", function (e) {
    e.preventDefault();
    var $button = $(this);
    var $results = $("#internal-link-analysis-results");
    var buttonText = internalLinkAnalysisData ? "Updating..." : "Analyzing...";

    $button.prop("disabled", true).text(buttonText);

    if (!internalLinkAnalysisData) {
      $results.html(
        "<p>Analysis in progress. This may take a few moments...</p>"
      );
    }

    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "analyze_internal_links",
      },
      success: function (response) {
        if (response.success) {
          internalLinkAnalysisData = response.data;
          renderInternalLinkAnalysis();
          $(
            "#delete-internal-link-analysis, #download-internal-link-analysis, #download-internal-link-analysis-svg"
          ).show();
        } else {
          $results.html(
            "<p>Failed to analyze internal links. Please try again.</p>"
          );
        }
      },
      error: function () {
        $results.html(
          "<p>An error occurred while analyzing internal links. Please try again.</p>"
        );
      },
      complete: function () {
        $button.prop("disabled", false).text("Update Analysis");
      },
    });
  });

  function initializeInternalLinkAnalysis() {
    renderInternalLinkAnalysis();
    $("#analyze-internal-links").text("Update Analysis");
    $(
      "#delete-internal-link-analysis, #download-internal-link-analysis, #download-internal-link-analysis-svg"
    ).show();
  }

  function renderInternalLinkAnalysis() {
    var $results = $("#internal-link-analysis-results");
    $results.empty();
    var diagramContainer = $('<div class="mermaid">').text(
      internalLinkAnalysisData
    );
    $results.append(diagramContainer);
    try {
      mermaid.init(undefined, $(".mermaid"));
    } catch (error) {
      console.error("Mermaid rendering error:", error);
      console.log("Diagram data:", internalLinkAnalysisData);
      $results.html(
        "<p>Failed to render internal link analysis. The data might be too complex or contain invalid syntax. Please try again with fewer content items.</p>"
      );
      $results.append("<pre>" + internalLinkAnalysisData + "</pre>");
    }
  }

  $("#delete-internal-link-analysis").on("click", function (e) {
    e.preventDefault();
    var $button = $(this);
    $button.prop("disabled", true).text("Deleting...");

    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "delete_internal_link_analysis",
      },
      success: function (response) {
        if (response.success) {
          var $results = $("#internal-link-analysis-results");
          $results.empty();
          internalLinkAnalysisData = null;
          $("#analyze-internal-links").text("Analyze Internal Links");
          $(
            "#delete-internal-link-analysis, #download-internal-link-analysis, #download-internal-link-analysis-svg"
          ).hide();
        } else {
          alert("Failed to delete the analysis. Please try again.");
        }
      },
      error: function () {
        alert(
          "An error occurred while deleting the analysis. Please try again."
        );
      },
      complete: function () {
        $button.prop("disabled", false).text("Delete Analysis");
      },
    });
  });

  $("#download-internal-link-analysis").on("click", function (e) {
    e.preventDefault();
    downloadDiagram("png", "internal-link-analysis");
  });

  $("#download-internal-link-analysis-svg").on("click", function (e) {
    e.preventDefault();
    downloadDiagram("svg", "internal-link-analysis");
  });

  function downloadDiagram(format, diagramType) {
    var $diagram =
      diagramType === "content-relationship-map"
        ? $("#content-relationship-map svg")
        : $("#internal-link-analysis-results svg");

    if ($diagram.length) {
      var svgData = new XMLSerializer().serializeToString($diagram[0]);
      if (format === "svg") {
        var blob = new Blob([svgData], { type: "image/svg+xml;charset=utf-8" });
        saveAs(blob, diagramType + ".svg");
      } else {
        var canvas = document.createElement("canvas");
        var ctx = canvas.getContext("2d");
        var img = new Image();
        img.onload = function () {
          canvas.width = img.width;
          canvas.height = img.height;
          ctx.drawImage(img, 0, 0);
          canvas.toBlob(function (blob) {
            saveAs(blob, diagramType + ".png");
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
            $("#new-blacklist-word").val("");
            refreshBlacklist();
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
          refreshBlacklist();
        } else {
          alert("Failed to remove word from blacklist.");
        }
      },
    });
  });

  // Search blacklist
  var searchTimer;
  $("#search-blacklist").on("input", function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
      refreshBlacklist();
    }, 300);
  });

  function refreshBlacklist(page = 1) {
    var search = $("#search-blacklist").val();
    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "search_blacklist",
        search: search,
        page: page,
      },
      success: function (response) {
        if (response.success) {
          $("#blacklist-words").html(response.data.blacklist_html);
          $("#blacklist-pagination").html(response.data.pagination_html);

          // Update the current page in the URL
          var newUrl = updateQueryStringParameter(
            window.location.href,
            "blacklist_page",
            page
          );
          history.pushState(null, "", newUrl);
        }
      },
    });
  }

  // Handle pagination clicks
  $(document).on("click", "#blacklist-pagination a", function (e) {
    e.preventDefault();
    var page = $(this).attr("href").split("blacklist_page=")[1];
    refreshBlacklist(page);
  });

  // Helper function to update URL parameters
  function updateQueryStringParameter(uri, key, value) {
    var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
    var separator = uri.indexOf("?") !== -1 ? "&" : "?";
    if (uri.match(re)) {
      return uri.replace(re, "$1" + key + "=" + value + "$2");
    } else {
      return uri + separator + key + "=" + value;
    }
  }
});
