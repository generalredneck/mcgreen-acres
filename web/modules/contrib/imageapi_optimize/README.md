# Image Optimize

The Image Optimize module provides a framework for image optimization.

It provides a system of 'pipelines' and each pipeline contains 'processors'.

To optimize an image they are passed to a pipeline which orchestrates each
processor applying itself to the image.

For example, you may have one pipeline for loss-less optimization that removes
unnecessary metadata from images, and a lossy pipeline that applies
re-compression via a third party webservice.

Built in support is provided for Drupal image styles, after the usual image
style has been applied the derivative image is passed into a configurable
pipeline for optimization.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/imageapi_optimize).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/imageapi_optimize).


## Table of contents

- Requirements
- Installation
- Configuration


## Requirements

The module does not have any direct requirements of its own, however, you will
need another module that provides a processor for optimizing images.

For example, you may one want of:

- [Image Optimize Binaries](https://www.drupal.org/project/imageapi_optimize_binaries)

- [Image Optimize reSmush.it](https://www.drupal.org/project/imageapi_optimize_resmushit)

- [Image Optimize TinyPNG](https://www.drupal.org/project/imageapi_optimize_tinypng)

- [Kraken](https://www.drupal.org/project/kraken)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

- Configure Image Optimize pipelines in Administration » Configuration » Media
  » Image Optimize pipelines:

  - Either add a new pipeline or edit an existing one.

- Configure which pipelines are used by image styles in Administration » Configuration » Media
  » Image styles:

  - You can set a sitewide default

  - Or edit each style individually to assign a pipeline.
