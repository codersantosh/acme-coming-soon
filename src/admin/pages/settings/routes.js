/* WordPress */
import { __ } from '@wordpress/i18n';
import { useContext } from '@wordpress/element';

/*Atrc*/
import {
	AtrcRoute,
	AtrcRoutes,
	AtrcNavigate,
	AtrcNav,
	AtrcWireFrameSidebarContent,
	AtrcFooterTemplate1,
	AtrcButtonSaveTemplate1,
} from 'atrc';

/*Inbuilt*/
import { Maintenance, Advanced } from './pages';
import { AtrcReduxContextData } from '../../routes';

/*Local*/
const SettingsRouters = () => {
	const data = useContext(AtrcReduxContextData);
	const { dbIsLoading, dbCanSave, dbSettings, dbSaveSettings } = data;
	return (
		<>
			<AtrcRoutes>
				<AtrcRoute
					exact
					path='general/*'
					element={<Maintenance />}
				/>
				<AtrcRoute
					exact
					path='advanced/*'
					element={<Advanced />}
				/>
				<AtrcRoute
					path='/'
					element={
						<AtrcNavigate
							to='general'
							replace
						/>
					}
				/>
			</AtrcRoutes>
			<AtrcFooterTemplate1 useDynamicPosition={true}>
				<AtrcButtonSaveTemplate1
					isLoading={dbIsLoading}
					canSave={dbCanSave}
					text={{
						saved: __('Saved', 'acme-coming-soon'),
						save: __('Save settings', 'acme-coming-soon'),
					}}
					disabled={dbIsLoading || !dbCanSave}
					onClick={() => dbSaveSettings(dbSettings)}
				/>
			</AtrcFooterTemplate1>
		</>
	);
};

const InitSettings = () => {
	return (
		<AtrcWireFrameSidebarContent
			wrapProps={{
				tag: 'div',
				className: 'at-ctnr-fld',
			}}
			rowProps={{}}
			renderSidebar={
				<AtrcNav
					variant='vertical'
					navs={[
						{
							to: 'general',
							children: __('General', 'acme-coming-soon'),
						},
						{
							to: 'advanced',
							children: __('Advanced', 'acme-coming-soon'),
						},
					]}
				/>
			}
			renderContent={<SettingsRouters />}
			contentProps={{
				tag: 'div',
				contentCol: 'at-col-10',
			}}
			sidebarProps={{
				sidebarCol: 'at-col-2',
			}}
		/>
	);
};

export default InitSettings;
