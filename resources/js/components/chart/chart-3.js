
export const initChartThree = () => {
    const chartElement = document.querySelector('#chartThree');

    if (chartElement) {
        const salesData = window.dashboardData?.monthlySales || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        const subtotalData = window.dashboardData?.monthlySubtotal || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

        const chartThreeOptions = {
            series: [{
                name: "Ventas (Total)",
                data: salesData,
            },
            {
                name: "Base Imponible (Subtotal)",
                data: subtotalData,
            },
            ],
            legend: {
                show: false,
                position: "top",
                horizontalAlign: "left",
            },
            colors: ["#465FFF", "#9CB9FF"],
            chart: {
                fontFamily: "Outfit, sans-serif",
                height: 310,
                type: "area",
                toolbar: {
                    show: false,
                },
            },
            fill: {
                gradient: {
                    enabled: true,
                    opacityFrom: 0.55,
                    opacityTo: 0,
                },
            },
            stroke: {
                curve: "straight",
                width: ["2", "2"],
            },
            markers: {
                size: 0,
            },
            labels: {
                show: false,
                position: "top",
            },
            grid: {
                xaxis: {
                    lines: {
                        show: false,
                    },
                },
                yaxis: {
                    lines: {
                        show: true,
                    },
                },
            },
            dataLabels: {
                enabled: false,
            },
            tooltip: {
                x: {
                    format: "dd MMM yyyy",
                },
            },
            xaxis: {
                type: "category",
                categories: [
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
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false,
                },
                tooltip: false,
            },
            yaxis: {
                title: {
                    style: {
                        fontSize: "0px",
                    },
                },
            },
        };

        const chart = new ApexCharts(chartElement, chartThreeOptions);
        chart.render();
        return chart;
    }
}

export default initChartThree;
