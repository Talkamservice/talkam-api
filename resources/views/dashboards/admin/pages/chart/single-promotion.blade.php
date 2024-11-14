<script>
    /* Audience report Chart */
    let promotionData = @json($promotion_data);
    let dataLabels = @json($data_labels);
    let period = @json($period);
    let promotionUUID = @json($promotion);

    var options = {
        series: [
            // {
            //     name: "Views",
            //     type: "column",
            //     data: promotionData, // Use actual promotion data from backend
            // },
            
            {
                name: "Views",
                type: "line",
                data: promotionData,
            },
            
        ],
        chart: {
            toolbar: {
                show: false,
            },
            type: "line",
            height: 250,
        },
        grid: {
            borderColor: "#f1f1f1",
            strokeDashArray: 3,
        },
        labels: dataLabels, // Use actual data labels from backend
        dataLabels: {
            enabled: false,
        },
        stroke: {
            width: [1], // Adjusted for single data series
            curve: ["straight"],
        },
        legend: {
            show: true,
            position: "top",
        },
        xaxis: {
            axisBorder: {
                color: "#e9e9e9",
            },
        },
        plotOptions: {
            bar: {
                columnWidth: "20%",
                borderRadius: 2,
            },
        },
        colors: ["rgba(132, 90, 223, 1)"],
    };

    document.querySelector("#audienceReport").innerHTML = "";
    var chart2 = new ApexCharts(document.querySelector("#audienceReport"), options);
    chart2.render();

    function audienceReport() {
        chart2.updateOptions({
            colors: ["rgba(" + myVarVal + ", 1)"],
        });
    }
    /* Audience report Chart */
</script>
