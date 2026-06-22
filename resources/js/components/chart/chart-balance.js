
export const initChartBalance = () => {
    const chartElement = document.querySelector('#chartBalance');

    if (chartElement) {
        const income = window.dashboardData?.financialData?.income || 0;
        const expense = window.dashboardData?.financialData?.expense || 0;

        // Si ambos son 0, mostrar una serie vacía o un placeholder para evitar errores de renderizado
        const seriesData = (income === 0 && expense === 0) ? [0.1, 0.1] : [income, expense];

        const chartOptions = {
            series: seriesData,
            chart: {
                type: 'donut',
                width: '100%',
                height: 300,
                fontFamily: "Outfit, sans-serif",
            },
            colors: ['#039855', '#D92D20'], // Verde para ingresos, Rojo para egresos
            labels: ['Ingresos', 'Egresos'],
            legend: {
                show: false,
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '75%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                fontSize: '14px',
                                fontWeight: 500,
                                offsetY: -10,
                            },
                            value: {
                                show: true,
                                fontSize: '24px',
                                fontWeight: 700,
                                offsetY: 10,
                                formatter: function (val) {
                                    if (income === 0 && expense === 0 && (val === 0.1)) return "S/ 0";
                                    return "S/ " + val.toLocaleString();
                                }
                            },
                            total: {
                                show: true,
                                label: 'Balance',
                                color: '#1D2939',
                                formatter: function (w) {
                                    const bal = window.dashboardData?.financialData?.balance || 0;
                                    return "S/ " + bal.toLocaleString();
                                }
                            }
                        }
                    }
                }
            },
            stroke: {
                show: false,
            },
            dataLabels: {
                enabled: false,
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 250
                    },
                }
            }]
        };

        const chart = new ApexCharts(chartElement, chartOptions);
        chart.render();
        return chart;
    }
}

export default initChartBalance;
