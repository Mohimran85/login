// sidebar toggle
var sidebarOpen = false;
var sidebar = document.getElementById("sidebar");

function openSidebar() {
  if (!sidebarOpen) {
    sidebar.classList.add("sidebar-responsive");
    sidebarOpen = true;
  }
}
function closeSidebar() {
  if (sidebarOpen) {
    sidebar.classList.remove("sidebar-responsive");
    sidebarOpen = false;
  }
}

// ------------------------ CHARTS -----------------------
// Debug: Log the data being passed from PHP
console.log("Category Data:", window.categoryData);
console.log("Category Counts:", window.categoryCounts);
console.log("Monthly Events:", window.monthlyEvents);
console.log("Monthly Participations:", window.monthlyParticipations);

// ---------------- BAR CHART ------------------------
var barChartOptions = {
  series: [
    {
      data:
        window.categoryCounts && window.categoryCounts.length > 0
          ? window.categoryCounts
          : [0],
    },
  ],
  chart: {
    type: "bar",
    height: 350,
  },
  plotOptions: {
    bar: {
      borderRadius: 5,
      borderRadiusApplication: "end",
      horizontal: true,
    },
  },
  dataLabels: {
    enabled: false,
  },
  xaxis: {
    categories:
      window.categoryData && window.categoryData.length > 0
        ? window.categoryData
        : ["No Event Data Available"],
  },
  title: {
    text: "Events by Category",
  },
};

const barChartEl = document.querySelector("#bar-chart");
if (barChartEl) {
  var chart = new ApexCharts(barChartEl, barChartOptions);
  chart.render();
}

// -------------Enhanced Monthly Trends Chart---------------
var areaChartOption = {
  series: [
    {
      name: `${window.currentYear || 2025} Student Events`,
      type: "area",
      data: window.monthlyEvents || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    },
    {
      name: `${window.currentYear || 2025} Student Participations`,
      type: "line",
      data: window.monthlyParticipations || [
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
      ],
    },
    {
      name: `${window.currentYear || 2025} Student Prize Winners`,
      type: "line",
      data: window.monthlyWins || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    },
  ],
  chart: {
    height: 380,
    type: "line",
    zoom: {
      enabled: true,
    },
    toolbar: {
      show: true,
      tools: {
        download: true,
        zoom: true,
        zoomin: true,
        zoomout: true,
        pan: true,
        reset: true,
      },
    },
  },
  stroke: {
    curve: "smooth",
    width: [3, 2, 2],
  },
  fill: {
    type: "gradient",
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.3,
      opacityTo: 0.9,
      stops: [0, 90, 100],
    },
    opacity: [0.3, 1, 1],
  },
  colors: ["#008FFB", "#00E396", "#FEB019"],
  labels: window.months || [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec",
  ],
  markers: {
    size: 4,
    strokeWidth: 2,
    hover: {
      size: 6,
    },
  },
  grid: {
    show: true,
    borderColor: "#e0e6ed",
    strokeDashArray: 5,
  },
  xaxis: {
    title: {
      text: `Months (${window.currentYear || new Date().getFullYear()})`,
      style: {
        fontSize: "12px",
        fontWeight: 600,
        color: "#374151",
      },
    },
    labels: {
      style: {
        colors: "#6B7280",
        fontSize: "11px",
      },
    },
  },
  yaxis: [
    {
      title: {
        text: "Events & Participations",
        style: {
          fontSize: "12px",
          fontWeight: 600,
          color: "#374151",
        },
      },
      labels: {
        style: {
          colors: "#6B7280",
          fontSize: "11px",
        },
        formatter: function (value) {
          return Math.round(value);
        },
      },
      min: 0,
    },
  ],
  tooltip: {
    shared: true,
    intersect: false,
    theme: "light",
    style: {
      fontSize: "12px",
    },
    x: {
      formatter: function (value, { dataPointIndex }) {
        const monthNames = [
          "January",
          "February",
          "March",
          "April",
          "May",
          "June",
          "July",
          "August",
          "September",
          "October",
          "November",
          "December",
        ];
        return (
          monthNames[dataPointIndex] +
          ` ${window.currentYear || new Date().getFullYear()}`
        );
      },
    },
    y: {
      formatter: function (value, { seriesIndex }) {
        if (seriesIndex === 0) return value + " events";
        if (seriesIndex === 1) return value + " participations";
        if (seriesIndex === 2) return value + " winners";
        return value;
      },
    },
    custom: function ({ series, seriesIndex, dataPointIndex, w }) {
      const monthNames = [
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
      ];

      // Check if this is YoY comparison mode (4 series) or overview mode (3 series)
      if (series.length === 4) {
        // Year-over-Year Comparison Mode
        const currentEvents = series[0][dataPointIndex];
        const prevEvents = series[1][dataPointIndex];
        const currentParticipations = series[2][dataPointIndex];
        const prevParticipations = series[3][dataPointIndex];

        return `
          <div style="padding: 12px; min-width: 220px;">
            <div style="font-weight: 600; margin-bottom: 8px; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px;">
              ${monthNames[dataPointIndex]}
            </div>
            <div style="margin-bottom: 6px;">
              <div style="margin-bottom: 2px;">
                <span style="color: #008FFB;">●</span> <strong>${window.currentYear} Events: ${currentEvents}</strong>
              </div>
              <div style="font-size: 11px; color: #6B7280; margin-left: 12px;">
                ${window.previousYear} Events: ${prevEvents}
              </div>
            </div>
            <div>
              <div style="margin-bottom: 2px;">
                <span style="color: #00E396;">●</span> <strong>${window.currentYear} Participations: ${currentParticipations}</strong>
              </div>
              <div style="font-size: 11px; color: #6B7280; margin-left: 12px;">
                ${window.previousYear} Participations: ${prevParticipations}
              </div>
            </div>
          </div>
        `;
      } else {
        // Overview Mode (3 series: Events, Participations, Prize Winners)
        const events = series[0][dataPointIndex];
        const participations = series[1][dataPointIndex];
        const wins = series[2][dataPointIndex];
        const successRate =
          participations > 0 ? ((wins / participations) * 100).toFixed(1) : "0";
        const avgParticipants =
          events > 0 ? (participations / events).toFixed(1) : "0";

        return `
          <div style="padding: 12px; min-width: 200px;">
            <div style="font-weight: 600; margin-bottom: 8px; color: #374151;">
              ${monthNames[dataPointIndex]} ${
                window.currentYear || new Date().getFullYear()
              }
            </div>
            <div style="margin-bottom: 4px;">
              <span style="color: #008FFB;">●</span> Events: <strong>${events}</strong>
            </div>
            <div style="margin-bottom: 4px;">
              <span style="color: #00E396;">●</span> Participations: <strong>${participations}</strong>
            </div>
            <div style="margin-bottom: 4px;">
              <span style="color: #FEB019;">●</span> Prize Winners: <strong>${wins}</strong>
            </div>
            <hr style="margin: 8px 0; border: none; border-top: 1px solid #e5e7eb;">
            <div style="font-size: 11px; color: #6B7280;">
              <div>Success Rate: ${successRate}%</div>
              <div>Avg Participants/Event: ${avgParticipants}</div>
            </div>
          </div>
        `;
      }
    },
  },
  legend: {
    show: true,
    position: "top",
    horizontalAlign: "center",
    floating: false,
    fontSize: "14px",
    fontWeight: 600,
    markers: {
      width: 12,
      height: 12,
      radius: 3,
      strokeWidth: 2,
    },
    itemMargin: {
      horizontal: 15,
      vertical: 5,
    },
    labels: {
      colors: "#374151",
      useSeriesColors: false,
    },
  },
  title: {
    text: `Monthly Event Trends - ${
      window.currentYear || new Date().getFullYear()
    }`,
    align: "left",
    style: {
      fontSize: "16px",
      fontWeight: 600,
      color: "#374151",
    },
  },
  subtitle: {
    text: "Track events, participations, and success rates throughout the year",
    align: "left",
    style: {
      fontSize: "12px",
      color: "#6B7280",
    },
  },
};

const areaChartEl = document.querySelector("#area-chart");
if (areaChartEl) {
  var areaChart = new ApexCharts(areaChartEl, areaChartOption);
  areaChart.render();
}

// ================== ENHANCED CATEGORY ANALYTICS ==================

// Enhanced Category Analytics Chart
let categoryChart;
let currentChartType = "bar";
let currentView = "participations";

// Initialize Enhanced Category Chart
function initEnhancedCategoryChart() {
  if (!window.categoryAnalytics || window.categoryAnalytics.length === 0) {
    console.log("No category analytics data available");
    return;
  }

  const chartData = prepareChartData("participations");

  const options = {
    series: chartData.series,
    chart: {
      type: "bar",
      height: 400,
      toolbar: {
        show: true,
        tools: {
          download: true,
          selection: false,
          zoom: false,
          zoomin: false,
          zoomout: false,
          pan: false,
          reset: false,
        },
      },
      animations: {
        enabled: true,
        easing: "easeinout",
        speed: 800,
        animateGradually: {
          enabled: true,
          delay: 150,
        },
        dynamicAnimation: {
          enabled: true,
          speed: 350,
        },
      },
    },
    plotOptions: {
      bar: {
        borderRadius: 8,
        columnWidth: "60%",
        distributed: true,
        dataLabels: {
          position: "top",
        },
      },
    },
    dataLabels: {
      enabled: true,
      formatter: function (val) {
        return val.toLocaleString();
      },
      offsetY: -20,
      style: {
        fontSize: "12px",
        fontWeight: "bold",
        colors: ["#333"],
      },
    },
    legend: {
      show: false,
    },
    xaxis: {
      categories: chartData.categories,
      labels: {
        style: {
          fontSize: "11px",
          fontWeight: "500",
        },
        rotate: -45,
      },
    },
    yaxis: {
      title: {
        text: chartData.yAxisTitle,
        style: {
          fontSize: "12px",
          fontWeight: "600",
        },
      },
      labels: {
        formatter: function (val) {
          return val.toLocaleString();
        },
      },
    },
    colors: [
      "#1e4276",
      "#2a5d8f",
      "#3a7bd5",
      "#4c8fe8",
      "#5ea2f5",
      "#70b5ff",
      "#82c8ff",
      "#94dbff",
      "#a6eeff",
      "#b8f1ff",
      "#caf4ff",
      "#dcf7ff",
      "#eefaff",
      "#F46036",
      "#E2C044",
    ],
    tooltip: {
      enabled: true,
      custom: function ({ series, seriesIndex, dataPointIndex, w }) {
        const category = window.categoryAnalytics[dataPointIndex];
        if (!category) return "";

        return `
          <div class="custom-tooltip">
            <div style="font-weight: bold; margin-bottom: 8px; color: #fff;">
              ${category.category_icon || "📊"} ${category.name}
            </div>
            <div style="margin-bottom: 4px;">
              <strong>Total Participations:</strong> ${category.total_participations.toLocaleString()}
            </div>
            <div style="margin-bottom: 4px;">
              <strong>Events:</strong> ${category.total_events}
            </div>
            <div style="margin-bottom: 4px;">
              <strong>Unique Participants:</strong> ${
                category.total_participants
              }
            </div>
            <div style="margin-bottom: 4px;">
              <strong>Success Rate:</strong> ${
                category.is_competitive
                  ? category.success_rate + "%"
                  : "N/A (Non-competitive)"
              }
            </div>
          </div>
        `;
      },
    },
    grid: {
      borderColor: "#f1f1f1",
      strokeDashArray: 3,
    },
    title: {
      text: "Events by Category - Interactive Analytics",
      align: "center",
      style: {
        fontSize: "16px",
        fontWeight: "600",
        color: "#333",
      },
    },
    subtitle: {
      text: "Click controls above to switch views • Hover for detailed insights",
      align: "center",
      style: {
        fontSize: "12px",
        color: "#666",
      },
    },
  };

  categoryChart = new ApexCharts(
    document.querySelector("#enhanced-category-chart"),
    options,
  );
  categoryChart.render();
}

// Prepare chart data based on selected view (Students Only)
function prepareChartData(view) {
  if (!window.categoryAnalytics) {
    return { series: [], categories: [], yAxisTitle: "" };
  }

  const categories = window.categoryAnalytics.map((cat) => cat.name);
  let series = [];
  let yAxisTitle = "";

  switch (view) {
    case "participations":
      series = [
        {
          name: "Total Participations",
          data: window.categoryAnalytics.map((cat) => cat.total_participations),
        },
      ];
      yAxisTitle = "Number of Participations";
      break;

    case "events":
      series = [
        {
          name: "Number of Events",
          data: window.categoryAnalytics.map((cat) => cat.total_events),
        },
      ];
      yAxisTitle = "Number of Events";
      break;

    case "success":
      // Filter to show only competitive events in success rate chart
      const competitiveCategories = window.categoryAnalytics.filter(
        (cat) => cat.is_competitive,
      );
      series = [
        {
          name: "Success Rate (%)",
          data: competitiveCategories.map((cat) => cat.success_rate),
        },
      ];
      // Store competitive categories for use by updateCategoryChart
      window.competitiveCategories = competitiveCategories;
      yAxisTitle = "Success Rate (%)";
      break;

    default:
      series = [
        {
          name: "Total Participations",
          data: window.categoryAnalytics.map((cat) => cat.total_participations),
        },
      ];
      yAxisTitle = "Number of Participations";
  }

  return { series, categories, yAxisTitle };
}

// Update category chart based on selected view (Students Only)
function updateCategoryChart() {
  const viewSelect = document.getElementById("categoryView");
  if (!viewSelect || !categoryChart) return;

  currentView = viewSelect.value;
  const chartData = prepareChartData(currentView);

  // Update chart type
  let chartType = currentChartType;

  categoryChart.updateOptions({
    series: chartData.series,
    chart: {
      type: chartType,
    },
    yaxis: {
      title: {
        text: chartData.yAxisTitle,
      },
    },
    plotOptions: {
      bar: {
        distributed: true, // Always distributed for student events
      },
    },
    colors: [
      "#1e4276",
      "#2a5d8f",
      "#3a7bd5",
      "#4c8fe8",
      "#5ea2f5",
      "#70b5ff",
      "#546E7A",
      "#D4526E",
      "#8D5B4C",
      "#F86624",
      "#D7263D",
      "#1B998B",
      "#2E294E",
      "#F46036",
      "#E2C044",
    ],
  });
}

// Toggle between chart types (Students Only)
function toggleChartType() {
  if (!categoryChart) return;

  currentChartType = currentChartType === "bar" ? "donut" : "bar";

  const chartData = prepareChartData(currentView);

  if (currentChartType === "donut") {
    categoryChart.updateOptions({
      chart: {
        type: "donut",
        height: 400,
      },
      series: chartData.series[0].data,
      labels: chartData.categories,
      plotOptions: {
        pie: {
          donut: {
            size: "70%",
            labels: {
              show: true,
              total: {
                show: true,
                label: "Total",
                formatter: function (w) {
                  return w.globals.seriesTotals
                    .reduce((a, b) => a + b, 0)
                    .toLocaleString();
                },
              },
            },
          },
        },
      },
      dataLabels: {
        enabled: true,
        formatter: function (val, opts) {
          return (
            opts.w.config.labels[opts.seriesIndex] + ": " + val.toFixed(1) + "%"
          );
        },
      },
      legend: {
        show: true,
        position: "bottom",
      },
    });
  } else {
    categoryChart.updateOptions({
      chart: {
        type: "bar",
        height: 400,
      },
      series: chartData.series,
      xaxis: {
        categories: chartData.categories,
      },
      plotOptions: {
        bar: {
          borderRadius: 8,
          columnWidth: "60%",
          distributed: true,
        },
      },
      dataLabels: {
        enabled: true,
        formatter: function (val) {
          return val.toLocaleString();
        },
      },
      legend: {
        show: false,
      },
    });
  }

  // Update button icon
  const toggleBtn = document.querySelector(
    ".chart-toggle .material-symbols-outlined",
  );
  if (toggleBtn) {
    toggleBtn.textContent =
      currentChartType === "bar" ? "donut_large" : "bar_chart";
  }
}

// Enhanced table interactions
function initCategoryTableInteractions() {
  const rows = document.querySelectorAll(".category-row");

  rows.forEach((row) => {
    row.addEventListener("click", function () {
      const categoryName = this.getAttribute("data-category");

      // Remove previous highlights
      rows.forEach((r) => r.classList.remove("highlighted"));

      // Highlight clicked row
      this.classList.add("highlighted");

      // You can add more interaction here, like showing detailed analytics
      console.log("Selected category:", categoryName);
    });
  });
}

// Initialize category analytics on page load
document.addEventListener("DOMContentLoaded", function () {
  // Initialize enhanced category chart
  if (typeof ApexCharts !== "undefined") {
    setTimeout(() => {
      initEnhancedCategoryChart();
      initCategoryTableInteractions();
      initDistributionChart();
    }, 500);
  }
});

// ================== DISTRIBUTION CHART FUNCTIONS (Students Only) ==================
let distributionChart = null;
let currentDistributionType = "student-detail";
let currentDistributionData = "events";

function initDistributionChart() {
  if (!window.distributionData) {
    console.warn("Distribution data not available");
    return;
  }

  createDistributionChart("student-detail");
}

function createDistributionChart(chartType) {
  const chartContainer = document.getElementById("distribution-chart");
  if (!chartContainer) {
    console.warn("Distribution chart container not found");
    return;
  }

  // Debug logging
  console.log("Creating distribution chart with type:", chartType);
  console.log("Monthly Events:", window.monthlyEvents);

  // Destroy existing chart
  if (distributionChart) {
    distributionChart.destroy();
  }

  const data = window.distributionData;
  let chartOptions = {};

  switch (chartType) {
    case "student-detail":
      // Detailed view of student events with zoom-to-weekly capability
      chartOptions = {
        series: [
          {
            name: "Student Events",
            data: window.monthlyEvents || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
          },
          {
            name: "Prize Winners",
            data: window.monthlyWins || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
          },
        ],
        chart: {
          type: "area",
          height: 400,
          stacked: false,
          zoom: {
            enabled: true,
            type: "x",
            autoScaleYaxis: true,
          },
          toolbar: {
            show: true,
            tools: {
              download: true,
              zoom: true,
              zoomin: true,
              zoomout: true,
              pan: true,
              reset: true,
            },
            autoSelected: "zoom",
          },
          events: {
            zoomed: function (chartContext, { xaxis, yaxis }) {
              console.log("Chart zoomed!");
              const range = xaxis.max - xaxis.min;
              console.log("Zoom range:", range);

              // If zoomed into 3 months or less, switch to weekly view
              if (range <= 3) {
                loadWeeklyData(Math.floor(xaxis.min), Math.floor(xaxis.max));
              }
            },
            beforeResetZoom: function (chartContext, opts) {
              console.log("Resetting zoom - back to monthly view");
              return {
                xaxis: {
                  min: undefined,
                  max: undefined,
                },
              };
            },
          },
          responsive: [
            {
              breakpoint: 768,
              options: {
                chart: {
                  height: 300,
                },
                legend: {
                  position: "bottom",
                },
              },
            },
          ],
        },
        xaxis: {
          categories: window.months || [
            "Jan",
            "Feb",
            "Mar",
            "Apr",
            "May",
            "Jun",
            "Jul",
            "Aug",
            "Sep",
            "Oct",
            "Nov",
            "Dec",
          ],
        },
        colors: ["#008FFB", "#FEB019"],
        fill: {
          type: "gradient",
          gradient: {
            opacityFrom: 0.6,
            opacityTo: 0.1,
          },
        },
        legend: {
          position: "top",
        },
        title: {
          text: "Student Events Performance (Zoom for Weekly View)",
          align: "left",
        },
        subtitle: {
          text: "Zoom into 3 months or less to see weekly breakdown",
          align: "left",
          style: {
            fontSize: "12px",
            color: "#6b7280",
          },
        },
        tooltip: {
          shared: true,
          intersect: false,
          y: {
            formatter: function (val, opts) {
              return val + (opts.seriesIndex === 0 ? " events" : " winners");
            },
          },
        },
      };
      break;

    case "timeline":
      // Timeline view showing student events over time
      chartOptions = {
        series: [
          {
            name: "Student Events",
            type: "column",
            data: window.monthlyEvents || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
          },
          {
            name: "Participations",
            type: "line",
            data: window.monthlyParticipations || [
              0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            ],
          },
        ],
        chart: {
          type: "line",
          height: 400,
          stacked: false,
        },
        stroke: {
          width: [0, 3],
          curve: "smooth",
        },
        plotOptions: {
          bar: {
            columnWidth: "50%",
          },
        },
        colors: ["#008FFB", "#00E396"],
        xaxis: {
          categories: window.months || [
            "Jan",
            "Feb",
            "Mar",
            "Apr",
            "May",
            "Jun",
            "Jul",
            "Aug",
            "Sep",
            "Oct",
            "Nov",
            "Dec",
          ],
        },
        legend: {
          position: "top",
        },
        title: {
          text: "Student Event Timeline",
          align: "left",
        },
      };
      break;

    case "performance":
      // Performance metrics view
      chartOptions = {
        series: [
          {
            name: "Success Rate (%)",
            data: (window.monthlyEvents || []).map((events, index) => {
              const wins = window.monthlyWins[index] || 0;
              const participations = window.monthlyParticipations[index] || 0;
              return participations > 0
                ? ((wins / participations) * 100).toFixed(1)
                : 0;
            }),
          },
        ],
        chart: {
          type: "line",
          height: 400,
          responsive: [
            {
              breakpoint: 768,
              options: {
                chart: {
                  height: 300,
                },
              },
            },
          ],
        },
        xaxis: {
          categories: window.months || [
            "Jan",
            "Feb",
            "Mar",
            "Apr",
            "May",
            "Jun",
            "Jul",
            "Aug",
            "Sep",
            "Oct",
            "Nov",
            "Dec",
          ],
          title: {
            text: "Months",
          },
        },
        yaxis: {
          title: {
            text: "Success Rate (%)",
          },
          max: 100,
        },
        colors: ["#FEB019"],
        stroke: {
          width: 3,
          curve: "smooth",
        },
        markers: {
          size: 6,
        },
        legend: {
          position: "top",
        },
        title: {
          text: "Student Event Success Rate Trend",
          align: "left",
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return val + "% success rate";
            },
          },
        },
      };
      break;
  }

  // Create and render chart
  try {
    console.log("Chart options for", chartType, ":", chartOptions);
    distributionChart = new ApexCharts(chartContainer, chartOptions);
    distributionChart
      .render()
      .then(() => {
        console.log("Chart rendered successfully for type:", chartType);
      })
      .catch((error) => {
        console.error("Error rendering chart:", error);
      });
    currentDistributionType = chartType;
  } catch (error) {
    console.error("Error creating chart:", error);
  }
}

function updateDistributionChart() {
  const selectElement = document.getElementById("distributionView");
  if (!selectElement) return;

  const selectedType = selectElement.value;
  createDistributionChart(selectedType);
}

function toggleDistributionData() {
  // Cycle through different view modes for student events only
  const viewModes = ["student-detail", "timeline", "performance"];
  const currentIndex = viewModes.indexOf(currentDistributionType);
  const nextIndex = (currentIndex + 1) % viewModes.length;
  const nextMode = viewModes[nextIndex];

  // Update dropdown
  const selectElement = document.getElementById("distributionView");
  if (selectElement) {
    selectElement.value = nextMode;
  }

  // Update chart
  createDistributionChart(nextMode);

  // Update toggle button icon
  const toggleBtn = document.querySelector(".distribution-toggle");
  if (toggleBtn) {
    const icon = toggleBtn.querySelector(".material-symbols-outlined");
    const icons = ["school", "timeline", "trending_up"];
    icon.textContent = icons[nextIndex];
  }
}

// Make functions globally available
window.updateDistributionChart = updateDistributionChart;
window.toggleDistributionData = toggleDistributionData;

// ================== ENHANCED MONTHLY TRENDS FUNCTIONS ==================

// Month details functionality
function showMonthDetails(monthIndex) {
  const monthNames = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];

  // Get data for the selected month (monthIndex is 1-based, arrays are 0-based)
  const events = window.monthlyEvents[monthIndex - 1] || 0;
  const participations = window.monthlyParticipations[monthIndex - 1] || 0;
  const wins = window.monthlyWins[monthIndex - 1] || 0;
  const successRate =
    participations > 0 ? ((wins / participations) * 100).toFixed(1) : 0;

  // Update month details panel with null checks
  const monthTitle = document.getElementById("selected-month-title");
  const monthEventsEl = document.getElementById("month-events");
  const monthParticipantsEl = document.getElementById("month-participants");
  const monthWinnersEl = document.getElementById("month-winners");
  const monthSuccessEl = document.getElementById("month-success");
  const monthPanel = document.getElementById("month-details-panel");

  if (monthTitle) {
    monthTitle.textContent = `${
      monthNames[monthIndex - 1]
    } ${window.currentYear} Details`;
  }
  if (monthEventsEl) monthEventsEl.textContent = events;
  if (monthParticipantsEl) monthParticipantsEl.textContent = participations;
  if (monthWinnersEl) monthWinnersEl.textContent = wins;
  if (monthSuccessEl) monthSuccessEl.textContent = successRate + "%";

  // Show the panel
  if (monthPanel) monthPanel.style.display = "block";

  // Highlight the selected month button
  const monthButtons = document.querySelectorAll(".month-btn");
  if (monthButtons.length > 0) {
    monthButtons.forEach((btn) => btn.classList.remove("active"));
  }
  const selectedBtn = document.querySelector(`[data-month="${monthIndex}"]`);
  if (selectedBtn) {
    selectedBtn.classList.add("active");
  }
}

function hideMonthDetails() {
  document.getElementById("month-details-panel").style.display = "none";
  document
    .querySelectorAll(".month-btn")
    .forEach((btn) => btn.classList.remove("active"));
}

// Trend chart view updater
function updateTrendChart() {
  const viewSelect = document.getElementById("trendView");
  if (!viewSelect) return;

  const selectedView = viewSelect.value;
  console.log("Updating trend chart to view:", selectedView);

  // You can add different chart configurations based on the view
  switch (selectedView) {
    case "overview":
      // Default view - reset to original configuration
      updateAreaChartForOverview();
      break;
    case "detailed":
      // Show more detailed metrics
      updateAreaChartForDetailedView();
      break;
    case "comparison":
      // Show year-over-year comparison
      updateAreaChartForComparisonView();
      break;
  }
}

function updateAreaChartForOverview() {
  // Reset to default view with clear current year labels
  if (typeof areaChart !== "undefined") {
    areaChart.updateOptions({
      colors: ["#008FFB", "#00E396", "#FEB019"],
      stroke: {
        curve: "smooth",
        width: [3, 2, 2],
        dashArray: [0, 0, 0],
      },
      markers: {
        size: [5, 4, 4],
      },
      title: {
        text: `Monthly Student Event Trends - ${window.currentYear || 2025}`,
        style: {
          fontSize: "16px",
          fontWeight: 600,
          color: "#374151",
        },
      },
    });

    areaChart.updateSeries([
      {
        name: `${window.currentYear || 2025} Student Events`,
        type: "area",
        data: window.monthlyEvents || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
      },
      {
        name: `${window.currentYear || 2025} Student Participations`,
        type: "line",
        data: window.monthlyParticipations || [
          0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        ],
      },
      {
        name: `${window.currentYear || 2025} Student Prize Winners`,
        type: "line",
        data: window.monthlyWins || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
      },
    ]);
  }
}

function updateAreaChartForDetailedView() {
  // Add additional data series for detailed view - using only real data
  if (typeof areaChart !== "undefined") {
    areaChart.updateOptions({
      colors: ["#008FFB", "#00E396", "#FEB019", "#FF4560"],
      stroke: {
        curve: "smooth",
        width: [3, 2, 2, 2],
        dashArray: [0, 0, 0, 0],
      },
      title: {
        text: `Detailed Student Event Analysis - ${window.currentYear || 2025}`,
        style: {
          fontSize: "16px",
          fontWeight: 600,
          color: "#374151",
        },
      },
    });

    areaChart.updateSeries([
      {
        name: `${window.currentYear || 2025} Student Events`,
        type: "area",
        data: window.monthlyEvents || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
      },
      {
        name: `${window.currentYear || 2025} Student Participations`,
        type: "line",
        data: window.monthlyParticipations || [
          0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        ],
      },
      {
        name: `${window.currentYear || 2025} Student Prize Winners`,
        type: "line",
        data: window.monthlyWins || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
      },
      {
        name: "Avg Participation Rate per Event",
        type: "line",
        data: (window.monthlyEvents || []).map((events, index) => {
          const participations =
            (window.monthlyParticipations || [])[index] || 0;
          return events > 0 ? Math.round(participations / events) : 0;
        }),
      },
    ]);
  }
}

function updateAreaChartForComparisonView() {
  // Show comparison with previous year using real database data
  console.log(
    "YoY Comparison using real database data from",
    window.previousYear,
  );

  if (typeof areaChart !== "undefined") {
    // Update with distinct colors and styles for better differentiation
    areaChart.updateOptions({
      colors: ["#008FFB", "#B3D9FF", "#00E396", "#B3F5E0"], // Solid blue for current year events, light blue for previous year events, solid green for current participations, light green for previous participations
      stroke: {
        curve: "smooth",
        width: [4, 3, 4, 3], // Thicker lines for current year
        dashArray: [0, 8, 0, 8], // Solid for current year, dashed for previous year
      },
      markers: {
        size: [6, 4, 6, 4], // Larger markers for current year
      },
      fill: {
        type: ["gradient", "solid", "gradient", "solid"],
        gradient: {
          shadeIntensity: 1,
          opacityFrom: [0.4, 0, 0.3, 0],
          opacityTo: [0.1, 0, 0.1, 0],
        },
        opacity: [0.35, 0.15, 0.35, 0.15], // More opacity for current year
      },
      legend: {
        position: "top",
        horizontalAlign: "center",
        fontSize: "13px",
        markers: {
          width: 12,
          height: 12,
        },
        itemMargin: {
          horizontal: 10,
          vertical: 5,
        },
      },
      title: {
        text: `Year-over-Year Comparison: ${window.currentYear} (Solid) vs ${window.previousYear} (Dashed)`,
        style: {
          fontSize: "16px",
          fontWeight: 600,
          color: "#374151",
        },
      },
      tooltip: {
        shared: true,
        intersect: false,
        x: {
          formatter: function (value, { dataPointIndex }) {
            const monthNames = [
              "January",
              "February",
              "March",
              "April",
              "May",
              "June",
              "July",
              "August",
              "September",
              "October",
              "November",
              "December",
            ];
            return monthNames[dataPointIndex];
          },
        },
      },
    });

    areaChart.updateSeries([
      {
        name: `${window.currentYear} Events`,
        type: "area",
        data: window.monthlyEvents || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
      },
      {
        name: `${window.previousYear} Events`,
        type: "line",
        data: window.previousYearEvents || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
      },
      {
        name: `${window.currentYear} Participations`,
        type: "area",
        data: window.monthlyParticipations || [
          0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        ],
      },
      {
        name: `${window.previousYear} Participations`,
        type: "line",
        data: window.previousYearParticipations || [
          0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        ],
      },
    ]);
  }
}

function refreshTrendData() {
  // Simulate data refresh with animation
  const refreshBtn = document.querySelector(".trend-refresh");
  if (refreshBtn) {
    refreshBtn.style.transform = "rotate(360deg)";
    refreshBtn.style.transition = "transform 0.5s ease";

    setTimeout(() => {
      refreshBtn.style.transform = "rotate(0deg)";
      // You could make an AJAX call here to refresh real data
      console.log("Trend data refreshed!");
    }, 500);
  }
}

// Load weekly data when zoomed
function loadWeeklyData(startMonth, endMonth) {
  console.log(`Loading weekly data from month ${startMonth} to ${endMonth}`);

  const year = window.currentYear || new Date().getFullYear();

  // Fetch weekly data via AJAX
  fetch(
    `ajax/get_weekly_data.php?year=${year}&start_month=${startMonth}&end_month=${endMonth}`,
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success && distributionChart) {
        console.log("Weekly data loaded:", data);

        // Update chart with weekly data
        distributionChart.updateOptions({
          xaxis: {
            categories: data.weeks,
          },
          title: {
            text: "Student Events Performance (Weekly View)",
            align: "left",
          },
          subtitle: {
            text: `Week-by-week breakdown for ${data.date_range}`,
            align: "left",
            style: {
              fontSize: "12px",
              color: "#6b7280",
            },
          },
        });

        distributionChart.updateSeries([
          {
            name: "Student Events",
            data: data.weekly_events,
          },
          {
            name: "Prize Winners",
            data: data.weekly_wins,
          },
        ]);
      }
    })
    .catch((error) => {
      console.error("Error loading weekly data:", error);
    });
}
