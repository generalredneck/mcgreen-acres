(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.salesChartWeekday = {
    attach(context, settings) {
      const d = settings.salesChartWeekday;
      if (!d) return;

      once('sales-chart-weekday-init', '#sales-chart-weekday', context).forEach((canvas) => {
        const fmt = (v) =>
          v.toLocaleString('en-US', {
            style: 'currency',
            currency: d.currency,
            minimumFractionDigits: 2,
          });

        new Chart(canvas, {
          type: 'bar',
          data: {
            labels: d.labels,
            datasets: [
              {
                label: 'Average Daily Revenue',
                data: d.avgValues,
                backgroundColor: 'rgba(68, 114, 196, 0.65)',
                borderColor: 'rgba(68, 114, 196, 1)',
                borderWidth: 1,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
              legend: {
                display: false,
              },
              tooltip: {
                callbacks: {
                  afterBody(tooltipItems) {
                    if (!tooltipItems.length) return [];
                    const idx = tooltipItems[0].dataIndex;
                    return [
                      '',
                      `Total: ${fmt(d.totalValues[idx])}`,
                      `Days counted: ${d.dayCounts[idx]}`,
                    ];
                  },
                  label(ctx) {
                    return `Avg: ${fmt(ctx.parsed.y)}`;
                  },
                },
              },
            },
            scales: {
              y: {
                ticks: {
                  callback: (v) => fmt(v),
                },
              },
            },
          },
        });
      });
    },
  };
})(Drupal, drupalSettings, once);
