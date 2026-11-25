import { __ } from '@wordpress/i18n';
import { useContext } from '@wordpress/element';

/*Atrc*/
import {
	Route as AtrcRoute,
	Routes as AtrcRoutes,
	Navigate as AtrcNavigate,
} from 'react-router-dom';
import AtrcNav from 'atrc/build/molecules/nav';
import AtrcWireFrameSidebarContent from 'atrc/build/wireframe/sidebar-content';
import AtrcFooterTemplate1 from 'atrc/build/templates/footers/template-1';
import AtrcButtonSaveTemplate1 from 'atrc/build/templates/components/button-save/template-1';

/*Inbuilt*/
import { AtrcReduxContextData } from '../../routes';
import Maintenance from './pages/maintenance';
import Advanced from './pages/advanced';

/*Local*/
const SettingsRouters = () => {
	const data = useContext( AtrcReduxContextData );
	const { dbIsLoading, dbCanSave, dbSettings, dbSaveSettings } = data;
	return (
		<>
			<AtrcRoutes>
				<AtrcRoute exact path="general/*" element={ <Maintenance /> } />
				<AtrcRoute exact path="advanced/*" element={ <Advanced /> } />
				<AtrcRoute
					path="/"
					element={ <AtrcNavigate to="general" replace /> }
				/>
			</AtrcRoutes>
			<AtrcFooterTemplate1 useDynamicPosition={ true }>
				<AtrcButtonSaveTemplate1
					isLoading={ dbIsLoading }
					canSave={ dbCanSave }
					text={ {
						saved: __( 'Saved', 'acme-coming-soon' ),
						save: __( 'Save settings', 'acme-coming-soon' ),
					} }
					disabled={ dbIsLoading || ! dbCanSave }
					onClick={ () => dbSaveSettings( dbSettings ) }
				/>
			</AtrcFooterTemplate1>
		</>
	);
};

const InitSettings = () => {
	return (
		<AtrcWireFrameSidebarContent
			wrapProps={ {
				tag: 'div',
				className: 'at-ctnr-fld',
			} }
			rowProps={ {} }
			renderSidebar={
				<AtrcNav
					variant="vertical"
					navs={ [
						{
							to: 'general',
							children: __( 'General', 'acme-coming-soon' ),
						},
						{
							to: 'advanced',
							children: __( 'Advanced', 'acme-coming-soon' ),
						},
					] }
				/>
			}
			renderContent={ <SettingsRouters /> }
			contentProps={ {
				tag: 'div',
				contentCol: 'at-col-10',
			} }
			sidebarProps={ {
				sidebarCol: 'at-col-2',
			} }
		/>
	);
};

export default InitSettings;
