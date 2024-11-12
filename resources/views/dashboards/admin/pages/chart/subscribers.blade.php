<script>
    // let subscriptionCounts = [10, 30, 100, 200, 150, 30, 200];
    let subscriptionCounts = @json($subscriptionCounts);
    let subscriptionRevenue = @json($subscriptionRevenue);
    console.log("Subscription Counts:", @json($subscriptionCounts));

    let daysOfWeek = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

    var options1 = {
        series: [{
                name: "Profit Earned",
                data: subscriptionRevenue,
            },
            {
                name: "Subscribers",
                data: subscriptionCounts,
            },
        ],
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
        colors: ["rgb(132, 90, 223)", "#e4e7ed"], // Colors for each series
        plotOptions: {
            bar: {
                colors: {
                    ranges: [{
                            from: -100,
                            to: -46,
                            color: "#ebeff5",
                        },
                        {
                            from: 2,
                            to: 0,
                            color: "#ebeff5",
                        },
                    ],
                },
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
            colors: ["#132B50", "#e4e7ed"], // Stroke colors for each series
        },
        legend: {
            show: true,
            position: "top",
        },
        yaxis: {
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
                    return y.toFixed(0) + "";
                },
            },
        },
        xaxis: {
            categories: daysOfWeek, // Days of the week
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
    console.log(options1.series);
    chart1.render();

    function crmProfitsearned() {
        chart1.updateOptions({
            colors: ["rgba(" + myVarVal + ", 1)", "#ededed"],
        });
    }
</script>
