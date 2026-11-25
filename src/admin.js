/* global AcmeComingSoonLocalize */
/* *********===================== Setup store ======================********* */
import { AtrcApis, AtrcStore, AtrcRegisterStore } from 'atrc/build/data';

AtrcApis.baseUrl( {
	key: 'acme-coming-soon-global-api-base-url',

	url: AcmeComingSoonLocalize.rest_url,
} );

/* Settings */
AtrcApis.register( {
	key: 'settings',
	path: 'acme-coming-soon/v1/settings',
	type: 'settings',
} );

/* Settings Local for user preferance */
AtrcStore.register( {
	key: 'acmeComingSoonLocal',
	type: 'localStorage',
} );

AtrcApis.xWpNonce( AcmeComingSoonLocalize.nonce );
window.atrcStore = AtrcRegisterStore( AcmeComingSoonLocalize.store );

import './admin/routes';
