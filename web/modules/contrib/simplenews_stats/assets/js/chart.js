/* global Chart */

(function (Drupal, once) {
  function SimplenewsStatsChart(element, settings) {
    this.element = element;

    // Merge options.
    this.options = this.default_options;
    Object.entries(settings).forEach(([property, setting]) => {
      this.options[property] = setting;
    });

    this.chart = undefined;

    this.init();
  }

  SimplenewsStatsChart.prototype = {
    default_options: {
      type: 'line',
      datasets: [],
      labels: [],
    },
    init() {
      if (typeof Chart === 'undefined') {
        console.warn('Chart.js is not loaded');
        return;
      }

      this.chart = new Chart(this.element, {
        type: this.options.type,
        data: this.options,
        options: {
          scales: {
            y: {
              beginAtZero: true,
            },
          },
        },
      });
    },
  };

  Drupal.behaviors.simplenews_stats_chart = {
    attach(context, settings) {
      const stats = settings.simplenews_stats;

      if (stats && typeof stats === 'object') {
        Object.entries(stats).forEach(([selector, data]) => {
          const elements = once('simplenews_charts', `#${selector}`, context);
          for (let i = 0; i < elements.length; i++) {
            new SimplenewsStatsChart(elements[i], data);
          }
        });
      }
    },
  };
})(Drupal, once);
