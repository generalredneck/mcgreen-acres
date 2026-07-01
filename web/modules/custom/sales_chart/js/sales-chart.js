(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.salesChart = {
    attach(context, settings) {
      const d = settings.salesChart;
      if (!d) return;

      once('sales-chart-init', '#sales-chart', context).forEach((canvas) => {
        // Build annotation objects for week and month boundary vertical lines.
        const annotations = {};

        d.weekBoundaries.forEach((idx, i) => {
          annotations[`wb_${i}`] = {
            type: 'line',
            xMin: idx - 0.5,
            xMax: idx - 0.5,
            borderColor: 'rgba(34, 139, 34, 0.25)',
            borderWidth: 1,
            borderDash: [4, 4],
          };
        });

        d.monthBoundaries.forEach((idx, i) => {
          annotations[`mb_${i}`] = {
            type: 'line',
            xMin: idx - 0.5,
            xMax: idx - 0.5,
            borderColor: 'rgba(200, 60, 60, 0.5)',
            borderWidth: 2,
          };
        });

        const fmt = (v) =>
          v.toLocaleString('en-US', {
            style: 'currency',
            currency: d.currency,
            minimumFractionDigits: 2,
          });

        new Chart(canvas, {
          data: {
            labels: d.labels,
            datasets: [
              {
                type: 'bar',
                label: 'Daily Revenue',
                data: d.dailyValues,
                backgroundColor: 'rgba(68, 114, 196, 0.65)',
                borderColor: 'rgba(68, 114, 196, 1)',
                borderWidth: 1,
                order: 3,
              },
              {
                type: 'line',
                label: 'Weekly Daily Avg',
                data: d.weeklyAvgValues,
                borderColor: 'rgba(34, 139, 34, 1)',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [6, 4],
                stepped: 'middle',
                pointRadius: 0,
                pointHoverRadius: 5,
                order: 2,
              },
              {
                type: 'line',
                label: 'Monthly Daily Avg',
                data: d.monthlyAvgValues,
                borderColor: 'rgba(200, 60, 60, 1)',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [6, 4],
                stepped: 'middle',
                pointRadius: 0,
                pointHoverRadius: 5,
                order: 1,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
              // 'index' mode collects all datasets at the hovered x position.
              mode: 'index',
              intersect: false,
            },
            plugins: {
              legend: {
                position: 'top',
              },
              tooltip: {
                callbacks: {
                  // Append week/month totals as extra tooltip rows using
                  // afterBody so they appear once rather than per-dataset.
                  afterBody(tooltipItems) {
                    if (!tooltipItems.length) return [];
                    const idx = tooltipItems[0].dataIndex;
                    return [
                      '',
                      `${d.weeklyPeriodLabels[idx]} total: ${fmt(d.weeklyTotalValues[idx])}`,
                      `${d.monthlyPeriodLabels[idx]} total: ${fmt(d.monthlyTotalValues[idx])}`,
                    ];
                  },
                  label(ctx) {
                    return `${ctx.dataset.label}: ${fmt(ctx.parsed.y)}`;
                  },
                },
              },
              annotation: {
                annotations,
              },
            },
            scales: {
              x: {
                ticks: {
                  maxRotation: 45,
                  autoSkip: true,
                  maxTicksLimit: 24,
                },
              },
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
