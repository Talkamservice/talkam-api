<script>
    // Retrieve monthly revenue data from backend
    let monthlyRevenue = @json($revenue_data['revenue']);

    // Function to format numbers with commas
    function formatNumber(number) {
        return number.toLocaleString();  // Formats number with commas (e.g., 23,092)
    }

    // ApexCharts configuration
    var options = {
        series: [{
            type: "line",
            name: "Revenue",
            data: monthlyRevenue.map((value, index) => ({
                x: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"][index],
                y: value
            })),
        }],
        chart: {
            height: 350,
            dropShadow: {
                enabled: true,
                blur: 3,
                color: "#000",
                opacity: 0.1,
            },
            animations: {
                speed: 500,
            },
        },
        colors: ["rgb(132, 90, 223)"],
        dataLabels: {
            enabled: false,
        },
        grid: {
            borderColor: "#f1f1f1",
            strokeDashArray: 3,
        },
        stroke: {
            curve: "smooth",
            width: 2,
        },
        xaxis: {
            categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
            title: {
                text: "Month",
            },
        },
        yaxis: {
            labels: {
                formatter: function(value) {
                    return "$" + formatNumber(value);
                },
            },
            title: {
                text: "Revenue (USD)",
            },
        },
        tooltip: {
            y: {
                formatter: function(value) {
                    return "USD" + formatNumber(value);
                }
            }
        },
        legend: {
            show: true,
            customLegendItems: ["Revenue"],
        },
        title: {
            text: "Revenue Analytics (USD)",
            align: "left",
            style: {
                fontSize: ".8125rem",
                fontWeight: "semibold",
                color: "#8c9097",
            },
        },
        markers: {
            hover: {
                sizeOffset: 5,
            },
        },
    };

    // Render the chart
    document.getElementById('crm-revenue-analytics').innerHTML = '';
    var chart = new ApexCharts(document.querySelector("#crm-revenue-analytics"), options);
    chart.render();
</script>
