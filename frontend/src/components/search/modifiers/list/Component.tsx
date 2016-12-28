/// <reference path='../../Search.d.ts' />
/// <reference path='./List.d.ts' />

import * as React from 'react';

import ModifierComponent from '../ModifierComponent';
import {PopupProps} from '../../popup/Popup';
import Item from './Item';

const ITEMS_COUNT = 6;
const MAX_ITEMS_COUNT = 10;

interface ListComponentState {
  currentIndex?: number;
}

export default class ListComponent extends ModifierComponent<ListComponentState> {

  state = {
    currentIndex: -1,
  };

  componentWillReceiveProps(nextProps: PopupProps) {
    if (this.props.token && nextProps.token && this.props.token.key !== nextProps.token.key) {
      this.setState({
        currentIndex: -1,
      });
    }
  }

  onUpClicked = () => {
    const len = this.getFlatList().length;
    if (!len) {
      return;
    }
    const currentIndex = this.state.currentIndex % len;

    this.setState({
      currentIndex: currentIndex <= 0
        ? len - 1
        : currentIndex - 1,
    });
  };

  onDownClicked = () => {
    const len = this.getFlatList().length;
    if (!len) {
      return;
    }
    const currentIndex = this.state.currentIndex;

    this.setState({
      currentIndex: currentIndex >= (len - 1)
        ? 0
        : currentIndex + 1,
    });
  };

  onSelect = () => {
    const { currentIndex } = this.state;
    const { activeTokenIndex, onChangeTokenModel } = this.props;

    const list = this.getFlatList();

    if (currentIndex === -1) {
      return false;
    }

    const model = list[currentIndex];
    onChangeTokenModel(activeTokenIndex, model, true);
    return true;
  };

  onSelectItem = (index: number) => {
    this.setState({
      currentIndex: index,
    }, this.onSelect);
  }

  groupItemsBySection(hints: SearchConfigItemContent[]): GroupedItem[] {
    const groupedObject = hints
      .reduce((sum, item) => {
        const section = item.section ? item.section : 'untitled';

        if (sum[section]) {
          sum[section].push(item);
        } else {
          sum[section] = [item];
        }
        return sum;
      }, {} as {[section: string]: SearchConfigItemContent[]});

    const groupsCount = Object.keys(groupedObject).length;
    return Object.keys(groupedObject).map(key => {
      const section = key === 'untitled' ? '' : key;

      const priority =
        section === '' ? 1
        : section === 'modifiers' ? 2
          : section === 'time' ? 20
            : 10;
      const list = groupsCount > 1
        ? groupedObject[key].slice(0, ITEMS_COUNT)
        : groupedObject[key].slice(0, MAX_ITEMS_COUNT);

      return {
        section: section,
        priority: priority,
        list: list,
      };
    });
  }

  getGroupedList() {
    const { token, adapter } = this.props;
    const hints = adapter.getHints(token);
    const items = this.groupItemsBySection(hints);

    let t = 0;
    return items
      .sort((a, b) => (
        a.priority === b.priority
          ? a.section < b.section ? 1 : -1
          : b.priority - a.priority
      ))
      .reverse()
      .map(item => {
        item.list = item.list.map(content => {
          content.index = t++;
          return content;
        });
        return item;
      });
  }

  getFlatList() {
    return this.getGroupedList()
      .reduce((sum, item) => sum.concat(item.list), [] as SearchConfigItemContent[]);
  }

  render() {
    const { currentIndex } = this.state;

    const groupedList = this.getGroupedList();

    if (!groupedList.length) {
      return null;
    }

    return (
      <div className='Search-hintMenu-container'>
        <span className='Search-hintMenu-arrow' />
        <span className='Search-hintMenu-arrowBorder' />
        <div className='Search-hintMenu'>
          {groupedList.map((item, i) => (
            <Item
              key={item.section}
              currentIndex={currentIndex}
              item={item}
              onSelectItem={this.onSelectItem}
            />
          ))}
        </div>
      </div>
    );
  }

};
