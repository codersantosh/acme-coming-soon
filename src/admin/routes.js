/* global AcmeComingSoonLocalize */
/* CSS */
import './admin.scss';

/* WordPress */
import {
	createContext,
	createRoot,
	lazy,
	Suspense,
	useContext,
} from '@wordpress/element';

/* Library */
import { map, isEmpty } from 'lodash';

/* Atrc: use direct module paths so unused controls are not evaluated. */
import {
	HashRouter as AtrcHashRouter,
	Route as AtrcRoute,
	Routes as AtrcRoutes,
} from 'react-router-dom';
import AtrcWrap from 'atrc/build/atoms/wrap';
import AtrcNotice from 'atrc/build/molecules/notice';
import AtrcWrapFloating from 'atrc/build/molecules/wrap-floating';
import AtrcMain from 'atrc/build/organisms/main';

import AtrcApplyWithSettings from 'atrc/build/data/hoc-settings';

/* Inbuilt */
import AdminHeader from './components/organisms/admin-header';

const Landing = lazy( () => import( './pages/landing' ) );
const Settings = lazy( () => import( './pages/settings/routes' ) );

const RouteFallback = () => (
	<AtrcWrap className="at-p" aria-busy="true" aria-live="polite">
		Loading…
	</AtrcWrap>
);

/* ==============Create Local Storage and Database Settings Context================== */
export const AtrcReduxContextData = createContext();

/* Admin routes pages */
const AdminRoutes = () => {
	const data = useContext( AtrcReduxContextData );
	const { dbNotices, dbRemoveNotice } = data;

	return (
		<>
			<AdminHeader />
			<AtrcMain>
				<Suspense fallback={ <RouteFallback /> }>
					<AtrcRoutes>
						<AtrcRoute index element={ <Landing /> } />
						<AtrcRoute
							exact
							path="/maintenance/*"
							element={ <Settings /> }
						/>
					</AtrcRoutes>
				</Suspense>
				{ ! isEmpty( dbNotices ) ? (
					<AtrcWrapFloating>
						{ map( dbNotices, ( value, key ) => (
							<AtrcNotice
								key={ key }
								autoDismiss={ 5000 }
								onAutoRemove={ () => dbRemoveNotice( key ) }
								onRemove={ () => dbRemoveNotice( key ) }
							>
								{ value.message }
							</AtrcNotice>
						) ) }
					</AtrcWrapFloating>
				) : null }
			</AtrcMain>
		</>
	);
};

/* Initilize database settings and add Context data*/
const InitDatabaseSettings = ( props ) => {
	const {
		isLoading,
		canSave,
		settings,
		updateSetting,
		saveSettings,
		notices,
		removeNotice,
		lsSettings,
		lsUpdateSetting,
		lsSaveSettings,
	} = props;

	const dbProps = {
		dbIsLoading: isLoading,
		dbCanSave: canSave,
		dbSettings: settings,
		dbUpdateSetting: updateSetting,
		dbSaveSettings: saveSettings,
		dbNotices: notices,
		dbRemoveNotice: removeNotice,
		lsSettings,
		lsUpdateSetting,
		lsSaveSettings,
	};
	return (
		<AtrcReduxContextData.Provider value={ { ...dbProps } }>
			<AtrcHashRouter basename="/">
				<AtrcWrap variant="wrp" className="at-box-szg at-m at-typ">
					<AdminRoutes />
				</AtrcWrap>
			</AtrcHashRouter>
		</AtrcReduxContextData.Provider>
	);
};
const InitDataBaseSettingsWithHoc =
	AtrcApplyWithSettings( InitDatabaseSettings );

/* Initilize local storage */
const InitLocalStorageSettings = ( props ) => {
	const { settings, updateSetting, saveSettings } = props;
	const defaultSettings = {
		gs1: true /* getting started 1 */,
	};
	return (
		<InitDataBaseSettingsWithHoc
			atrcStore="acme-coming-soon"
			atrcStoreKey="settings"
			lsSettings={ settings || defaultSettings }
			lsUpdateSetting={ updateSetting }
			lsSaveSettings={ saveSettings }
		/>
	);
};
const InitLocalStorageSettingsWithHoc = AtrcApplyWithSettings(
	InitLocalStorageSettings
);

/* Initilize react on oru div */
document.addEventListener( 'DOMContentLoaded', () => {
	const rootElement = document.getElementById(
		AcmeComingSoonLocalize.root_id
	);

	if ( rootElement ) {
		createRoot( rootElement ).render(
			<InitLocalStorageSettingsWithHoc
				atrcStore="acme-coming-soon"
				atrcStoreKey="acmeComingSoonLocal"
			/>
		);
	}
} );
