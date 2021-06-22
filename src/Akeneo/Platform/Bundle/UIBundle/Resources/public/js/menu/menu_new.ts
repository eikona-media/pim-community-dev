'use strict';

import {ViewOptions} from 'backbone';
import {NavigationEntry, PimNavigation} from '../PimNavigation';
import View from '../view/base-interface';
import React from 'react';
import {CardIcon} from 'akeneo-design-system';

const BaseForm = require('pim/form');
const _ = require('underscore');
const template = require('pim/template/menu/menu');

type EntryView = View & {
  config: {
    title: string;
  };
};

/**
 * Base extension for menu
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Menu extends BaseForm {
  template = _.template(template);

  constructor(options?: ViewOptions<any>) {
    super({
      ...options,
      className: 'AknHeader',
    });
  }

  /**
   * {@inheritdoc}
   */
  render() {
    if (!this.configured) {
      return this;
    }

    this.renderReact(
      PimNavigation,
      {
        entries: this.findMainEntries(),
      },
      this.el
    );

    return this;
  }

  /**
   * {@inheritdoc}
   */
  renderExtension(extension: any) {
    if (
      !_.isEmpty(extension.options.config) &&
      (!extension.options.config.to || extension.options.config.isLandingSectionPage) &&
      _.isFunction(extension.hasChildren) &&
      !extension.hasChildren()
    ) {
      return;
    }

    super.renderExtension(extension);
  }

  findMainEntries(): NavigationEntry[] {
    const extensions = Object.values(this.extensions).filter((extension: View) => {
      if (extension.targetZone !== 'mainMenu') {
        return false;
      }

      return extension.code !== 'pim-menu-logo';
    });

    const entries: NavigationEntry[] = extensions.map((extension: EntryView, index) => {
      const {title} = extension.config;

      return {
        code: extension.code,
        label: title,
        active: false,
        disabled: false,
        route: 'pim_settings_index',
        icon: React.createElement(CardIcon),
        position: index,
      };
    });

    entries.sort((entryA: NavigationEntry, entryB: NavigationEntry) => {
      return entryA.position - entryB.position;
    });

    return entries;
  }
}

export = Menu;
