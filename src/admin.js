/* *********===================== Setup store ======================********* */
import { AtrcApis, AtrcStore, AtrcRegisterStore } from 'atrc/build/data';

AtrcApis.baseUrl({
	key: 'atrc-global-api-base-url',
	// eslint-disable-next-line no-undef
	url: AcmeComingSoonLocalize.rest_url,
});

/* Settings */
AtrcApis.register({
	key: 'settings',
	path: 'acme-coming-soon/v1/settings',
	type: 'settings',
});

/* Settings Local for user preferance */
AtrcStore.register({
	key: 'acmeComingSoonLocal',
	type: 'localStorage',
});

// eslint-disable-next-line no-undef
AtrcApis.xWpNonce(AcmeComingSoonLocalize.nonce);
window.atrcStore = AtrcRegisterStore(AcmeComingSoonLocalize.store);

import './admin/routes';
