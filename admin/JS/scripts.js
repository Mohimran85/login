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
      data: window.categoryCounts || [0, 0, 0, 0],
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
    categories: window.categoryData || [
      "No Data",
      "Available",
      "Yet",
      "Loading",
    ],
  },
  title: {
    text: "Events by Category",
  },
};

var chart = new ApexCharts(
  document.querySelector("#bar-chart"),
  barChartOptions
);
chart.render();

// -------------Combo Charts---------------
var areaChartOption = {
  series: [
    {
      name: "Events Created",
      type: "area",
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
    height: 350,
    type: "line",
  },
  stroke: {
    curve: "smooth",
  },
  fill: {
    type: "solid",
    opacity: [0.35, 1],
  },
  labels: [
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
    size: 0,
  },
  yaxis: [
    {
      title: {
        text: "Events Created",
      },
    },
    {
      opposite: true,
      title: {
        text: "Total Participations",
      },
    },
  ],
  tooltip: {
    shared: true,
    intersect: false,
    y: {
      formatter: function (y) {
        if (typeof y !== "undefined") {
          return y.toFixed(0) + " events";
        }
        return y;
      },
    },
  },
  title: {
    text: "Monthly Event Trends",
  },
};

var chart = new ApexCharts(
  document.querySelector("#area-chart"),
  areaChartOption
);
chart.render();
