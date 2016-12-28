import { appHistory } from '../routes';

import config from '../config/config';
import { fetchCommits } from '../actions';
import { appStore } from '../stores';

const routes = config.routes;

export function filter() {
  if (appStore.page > 0) {
    appStore.setPage(0);
    appHistory.push(routes.home);
  }
  fetchCommits();
}
