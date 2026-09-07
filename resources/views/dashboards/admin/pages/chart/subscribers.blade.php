<script> 
    let subscriptionCounts = @json($subscriptionCounts);

    let monthsOfYear = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

    var options1 = {
        series: [{
            name: "Subscribers",
            data: subscriptionCounts,
        }],
        chart: {
            type: "bar",
            height: 180,
            toolbar: {
                show: false,
            },
        },
        grid: {
            borderColor: "#f1f1f1",
            strokeDashArray: 3,
        },
        colors: ["#132B50"], // Color for subscribers
        plotOptions: {
            bar: {
                columnWidth: "60%",
                borderRadius: 5,
            },
        },
        dataLabels: {
            enabled: false,
        },
        stroke: {
            show: true,
            width: 2,
            colors: ["#132B50"], // Stroke color for the series
        },
        legend: {
            show: true,
            position: "top",
        },
        yaxis: {
            min: 0,
            max: Math.max(...subscriptionCounts, 1),
            forceNiceScale: true,
            title: {
                style: {
                    color: "#adb5be",
                    fontSize: "13px",
                    fontFamily: "poppins, sans-serif",
                    fontWeight: 600,
                    cssClass: "apexcharts-yaxis-label",
                },
            },
            labels: {
                formatter: function(y) {
                    return Number.isFinite(y) ? y.toFixed(0) : "0";
                },
            },
        },
        xaxis: {
            categories: monthsOfYear, // Months of the year
            axisBorder: {
                show: true,
                color: "rgba(119, 119, 142, 0.05)",
                offsetX: 0,
                offsetY: 0,
            },
            axisTicks: {
                show: true,
                color: "rgba(119, 119, 142, 0.05)",
                width: 6,
                offsetX: 0,
                offsetY: 0,
            },
            labels: {
                rotate: -90,
            },
        },
    };

    document.getElementById('crm-profits-earned').innerHTML = '';
    var chart1 = new ApexCharts(document.querySelector("#crm-profits-earned"), options1);
    chart1.render();
</script>
