import { runInAction } from 'mobx';

import DetailsLevel from '../enums/DetailsLevel';
import { getDiff, getGitStatus } from './utils';
import CommitRow from '../entities/CommitRow';
import { CommitPanelStore } from '../stores/commitPanelStore';

type Store = CommitRow|CommitPanelStore;

export function changeDetailsLevel(detailsLevel: DetailsLevel, store: Store) {
  if (detailsLevel === DetailsLevel.Overview && 'gitStatus' in store && !(store as CommitPanelStore).gitStatus) {
    store.setLoading(true);
    getGitStatus()
      .then(handleSuccess(detailsLevel, store))
      .catch(handleError(detailsLevel, store));
    return;
  }

  if (detailsLevel === DetailsLevel.FullDiff && !store.diff) {
    store.setLoading(true);
    getDiff(store.hash)
      .then(handleSuccess(detailsLevel, store))
      .catch(handleError(detailsLevel, store));
    return;
  }

  runInAction(() => {
    store.setDetailsLevel(detailsLevel);
    store.setError(null);
    store.setLoading(false);
  });
}

function handleSuccess(detailsLevel: DetailsLevel, store: Store) {
  if (detailsLevel === DetailsLevel.Overview && 'gitStatus' in store) {
    return gitStatus => runInAction(() => {
      store.setDetailsLevel(detailsLevel);
      (store as CommitPanelStore).setGitStatus(gitStatus);
      store.setError(null);
      store.setLoading(false);
    });
  } else if (detailsLevel === DetailsLevel.FullDiff) {
    return diff => runInAction(() => {
      store.setDetailsLevel(detailsLevel);
      store.setDiff(diff);
      store.setError(null);
      store.setLoading(false);
    });
  }
}

function handleError(detailsLevel: DetailsLevel, store: Store) {
  return err => runInAction(() => {
    store.setDetailsLevel(detailsLevel);
    store.setError(err.message);
    store.setLoading(false);
  });
}
