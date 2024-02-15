/* WordPress */
import { __ } from '@wordpress/i18n';
import { useContext, useMemo } from '@wordpress/element';

/* Library */
import classNames from 'classnames';

import { map, cloneDeep } from 'lodash';

/*Atrc*/
import {
	AtrcControlText,
	AtrcText,
	AtrcControlSelect,
	AtrcControlToggle,
	AtrcWireFrameContentSidebar,
	AtrcWireFrameHeaderContentFooter,
	AtrcPrefix,
	AtrcControlSelectPost,
	AtrcPanelBody,
	AtrcPanelRow,
	AtrcRepeater,
	AtrcRepeaterGroup,
	AtrcRepeaterGroupAdd,
	AtrcNestedObjUpdateByKey1,
	AtrcNestedObjDeleteByKey2,
	AtrcNestedObjUpdateByKey2,
	AtrcNestedObjAddByKey1,
	AtrcContent,
	AtrcTitleTemplate1,
	AtrcTitleTemplate2,
} from 'atrc';

/* Inbuilt */
import { AtrcReduxContextData } from '../../../routes';

/*Local*/
const MainContent = () => {
	const data = useContext(AtrcReduxContextData);

	const { dbSettings, dbUpdateSetting } = data;

	const {
		on = false,
		access = 'admin',
		exclude = {
			searchBot: false,
			useRoles: [],
			ips: [],
			slugs: [],
		},
		template = 0,
		deleteAll = false,
	} = dbSettings;

	const userRolesOptions = useMemo(() => {
		return map(AcmeComingSoonLocalize.userRoles, (label, value) => ({
			value,
			label,
		}));
	}, [AcmeComingSoonLocalize.userRoles]);

	const updateSettingKey = (key, val) => {
		dbUpdateSetting(key, val);
	};

	return (
		<AtrcContent>
			<AtrcPanelRow>
				<AtrcControlToggle
					label={__(
						'Activate coming soon (Maintenance) mode',
						'acme-coming-soon'
					)}
					checked={on}
					onChange={() => updateSettingKey('on', !on)}
				/>
			</AtrcPanelRow>
			{on ? (
				<>
					<AtrcPanelRow>
						<AtrcControlSelect
							label={__('Access site', 'acme-coming-soon')}
							wrapProps={{
								className: 'at-flx-grw-1',
							}}
							value={access}
							options={[
								{
									value: '',
									label: __('None', 'acme-coming-soon'),
								},
								{
									value: 'admin',
									label: __('Admin only', 'acme-coming-soon'),
								},
								{
									value: 'login',
									label: __('Login user', 'acme-coming-soon'),
								},

								{
									value: 'roles',
									label: __('Selected roles', 'acme-coming-soon'),
								},
							]}
							onChange={(newVal) => updateSettingKey('access', newVal)}
						/>
					</AtrcPanelRow>
					{'roles' === access ? (
						<AtrcPanelRow>
							<AtrcControlSelect
								label={__(
									'Allow user roles during maintenance mode.',
									'acme-coming-soon'
								)}
								wrapProps={{
									className: 'at-flx-grw-1',
								}}
								value={exclude.useRoles}
								isMulti={true}
								multiValType='array'
								options={userRolesOptions}
								onChange={(newVal) => {
									const newExclude = AtrcNestedObjUpdateByKey1({
										settings: exclude,
										key1: 'useRoles',
										val1: newVal,
									});
									updateSettingKey('exclude', newExclude);
								}}
							/>
						</AtrcPanelRow>
					) : null}
					<AtrcPanelRow>
						<AtrcControlSelectPost
							label={__('Select maintenance page', 'acme-coming-soon')}
							wrapProps={{
								className: 'at-flx-grw-1',
							}}
							value={template}
							onChange={(newVal) => updateSettingKey('template', newVal)}
							postType='page'
						/>
					</AtrcPanelRow>
					<AtrcPanelRow>
						<AtrcPanelBody
							className={classNames(AtrcPrefix('m-0'), 'at-flx-grw-1')}
							title={__(
								'Exclude from Coming soon (Maintenance) mode',
								'acme-coming-soon'
							)}
							initialOpen={true}>
							<AtrcPanelRow>
								<AtrcControlToggle
									label={__(
										'Allow search bots during maintenance mode.',
										'acme-coming-soon'
									)}
									help={__(
										'Enable access for search bots while the website is in "Coming Soon" or maintenance mode.',
										'acme-coming-soon'
									)}
									checked={exclude.searchBot}
									onChange={() => {
										const newExclude = AtrcNestedObjUpdateByKey1({
											settings: exclude,
											key1: 'searchBot',
											val1: !exclude.searchBot,
										});
										updateSettingKey('exclude', newExclude);
									}}
								/>
							</AtrcPanelRow>

							<AtrcPanelRow>
								<AtrcPanelBody
									className={classNames(AtrcPrefix('m-0'), 'at-flx-grw-1')}
									title={__(
										'Allow IPs during maintenance mode.',
										'acme-coming-soon'
									)}
									initialOpen={true}>
									<AtrcRepeater
										label={__('Add IPs', 'patterns-store')}
										groups={() =>
											map(exclude.ips, (item, itemIndex) => (
												<AtrcRepeaterGroup
													key={`ip-${itemIndex}`}
													groupIndex={itemIndex}
													deleteGroup={(itmIndex) => {
														const updatedSettings = AtrcNestedObjDeleteByKey2({
															settings: exclude,
															key1: 'ips',
															key2: itemIndex,
														});
														dbUpdateSetting('exclude', updatedSettings);
													}}
													groupTitle={sprintf(
														// translators: %s: placeholder for idx
														__('IP %d', 'patterns-store'),
														itemIndex + 1
													)}
													deleteTitle={__('Remove item', 'patterns-store')}>
													<AtrcPanelRow className={classNames('at-m')}>
														<AtrcControlText
															label={__('IP', 'patterns-store')}
															help={__('Enter valid IP', 'acme-coming-soon')}
															value={item}
															onChange={(newVal) => {
																const updatedSettings =
																	AtrcNestedObjUpdateByKey2({
																		settings: exclude,
																		key1: 'ips',
																		key2: itemIndex,
																		val2: newVal,
																	});
																dbUpdateSetting('exclude', updatedSettings);
															}}
														/>
													</AtrcPanelRow>
												</AtrcRepeaterGroup>
											))
										}
										addGroup={() => (
											<AtrcRepeaterGroupAdd
												addGroup={() => {
													const addedSettings = AtrcNestedObjAddByKey1({
														settings: exclude,
														key1: 'ips',
														val1: '',
													});
													dbUpdateSetting('exclude', addedSettings);
												}}
												tooltipText={__('Add IP', 'patterns-store')}
												label={__('Add IP', 'patterns-store')}
											/>
										)}
									/>
								</AtrcPanelBody>
							</AtrcPanelRow>

							<AtrcPanelRow>
								<AtrcPanelBody
									className={classNames(AtrcPrefix('m-0'), 'at-flx-grw-1')}
									title={__(
										'Allow Slugs during maintenance mode.',
										'acme-coming-soon'
									)}
									initialOpen={true}>
									<AtrcRepeater
										label={__('Add slugs', 'patterns-store')}
										groups={() =>
											map(exclude.slugs, (item, itemIndex) => (
												<AtrcRepeaterGroup
													key={`ip-${itemIndex}`}
													groupIndex={itemIndex}
													deleteGroup={(itmIndex) => {
														const updatedSettings = AtrcNestedObjDeleteByKey2({
															settings: exclude,
															key1: 'slugs',
															key2: itemIndex,
														});
														dbUpdateSetting('exclude', updatedSettings);
													}}
													groupTitle={sprintf(
														// translators: %s: placeholder for idx
														__('Slug %d', 'patterns-store'),
														itemIndex + 1
													)}
													deleteTitle={__('Remove item', 'patterns-store')}>
													<AtrcPanelRow className={classNames('at-m')}>
														<AtrcControlText
															label={__('Slug', 'patterns-store')}
															help={__('Any path of the url', 'patterns-store')}
															value={item}
															onChange={(newVal) => {
																const updatedSettings =
																	AtrcNestedObjUpdateByKey2({
																		settings: exclude,
																		key1: 'slugs',
																		key2: itemIndex,
																		val2: newVal,
																	});
																dbUpdateSetting('exclude', updatedSettings);
															}}
														/>
													</AtrcPanelRow>
												</AtrcRepeaterGroup>
											))
										}
										addGroup={() => (
											<AtrcRepeaterGroupAdd
												addGroup={() => {
													const addedSettings = AtrcNestedObjAddByKey1({
														settings: exclude,
														key1: 'slugs',
														val1: '',
													});
													dbUpdateSetting('exclude', addedSettings);
												}}
												tooltipText={__('Add slug', 'patterns-store')}
												label={__('Add slug', 'patterns-store')}
											/>
										)}
									/>
								</AtrcPanelBody>
							</AtrcPanelRow>
						</AtrcPanelBody>
					</AtrcPanelRow>
				</>
			) : null}
		</AtrcContent>
	);
};

const Documentation = () => {
	const data = useContext(AtrcReduxContextData);

	const { lsSettings, lsSaveSettings } = data;

	return (
		<AtrcWireFrameHeaderContentFooter
			headerRowProps={{
				className: classNames(AtrcPrefix('header-docs'), 'at-m'),
			}}
			renderHeader={
				<AtrcTitleTemplate2
					title={__('Documentation', 'acme-coming-soon')}
					buttons={[
						{
							iconProps: {
								type: 'svg',
								svg: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="at-svg" viewBox="0 0 16 16"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg>',
							},
							onClick: () => {
								const localStorageClone = cloneDeep(lsSettings);
								localStorageClone.mnDocs1 = !localStorageClone.mnDocs1;
								lsSaveSettings(localStorageClone);
							},
							variant: 'danger',
							className: 'at-btn-close at-flx at-al-itm-ctr',
						},
					]}
				/>
			}
			renderContent={
				<>
					<AtrcPanelBody
						className={classNames(AtrcPrefix('m-0'))}
						title={__(
							'What is the "Coming Soon (Maintenance) mode"?',
							'acme-coming-soon'
						)}
						initialOpen={true}>
						<AtrcText
							tag='p'
							className={classNames(AtrcPrefix('m-0'), 'at-m')}>
							{__(
								'"Coming Soon (Maintenance) mode" is a feature that allows you to create a temporary maintenance page for your website while you work on new site, updates or changes. This page is displayed to visitors when they try to access your website, letting them know that your website is temporarily unavailable.',
								'acme-coming-soon'
							)}
						</AtrcText>
					</AtrcPanelBody>
					<AtrcPanelBody
						title={__(
							'Who can access the site during maintenance mode?',
							'acme-coming-soon'
						)}
						initialOpen={false}>
						<AtrcText
							tag='p'
							className={classNames(AtrcPrefix('m-0'), 'at-m')}>
							{__(
								"By default, only logged-in users with administrative privileges can access the site during maintenance mode. This is to ensure that visitors to your website don't see an unfinished or broken version of your site. Nevertheless, you have the flexibility to tailor this functionality and permit access to various user roles.",
								'acme-coming-soon'
							)}
						</AtrcText>
					</AtrcPanelBody>
					<AtrcPanelBody
						title={__(
							'What content is displayed during maintenance mode?',
							'acme-coming-soon'
						)}
						initialOpen={false}>
						<AtrcText
							tag='p'
							className={classNames(AtrcPrefix('m-0'), 'at-m')}>
							{__(
								'During maintenance mode, the content displayed on your website is determined by the maintenance page that you have selected. You can choose to display a simple message informing visitors that your website is currently undergoing maintenance, or you can add more detailed information about the updates or changes that you are making. You can also add your logo, branding, and other design elements to the maintenance page to make it more visually appealing. The design is on your hand.',
								'acme-coming-soon'
							)}
						</AtrcText>
					</AtrcPanelBody>
					<AtrcPanelBody
						title={__(
							'Can I exclude specific IP addresses, pages, or categories from Coming Soon Mode?',
							'acme-coming-soon'
						)}
						initialOpen={false}>
						<AtrcText
							tag='p'
							className={classNames(AtrcPrefix('m-0'), 'at-m')}>
							{__(
								'Yes, you can allow search bots, IPs, and specific pages or post categories via slug when the Coming Soon Mode is activated. Simply configure the exclusions in the plugin settings to grant access to specific entities during maintenance.',
								'acme-coming-soon'
							)}
						</AtrcText>
					</AtrcPanelBody>
				</>
			}
			allowHeaderRow={false}
			allowHeaderCol={false}
			allowContentRow={false}
			allowContentCol={false}
		/>
	);
};

const Settings = () => {
	const data = useContext(AtrcReduxContextData);
	const { dbIsLoading, dbCanSave, dbSettings, dbSaveSettings, lsSettings } =
		data;

	if (!dbSettings) {
		return null;
	}

	const { mnDocs1 } = lsSettings;

	return (
		<AtrcWireFrameHeaderContentFooter
			wrapProps={{
				className: classNames(AtrcPrefix('bg-white'), 'at-bg-cl'),
			}}
			renderHeader={
				<AtrcTitleTemplate1
					title={__(
						'Coming Soon and Maintenance Mode Page settings',
						'acme-coming-soon'
					)}
				/>
			}
			renderContent={
				<AtrcWireFrameContentSidebar
					wrapProps={{
						allowContainer: true,
						type: 'fluid',
						tag: 'section',
					}}
					renderContent={<MainContent />}
					renderSidebar={!mnDocs1 ? <Documentation /> : null}
					contentProps={{
						contentCol: mnDocs1 ? 'at-col-12' : 'at-col-7',
					}}
					sidebarProps={{
						sidebarCol: 'at-col-5',
					}}
				/>
			}
			allowHeaderRow={false}
			allowHeaderCol={false}
			allowContentRow={false}
			allowContentCol={false}
		/>
	);
};

export default Settings;
