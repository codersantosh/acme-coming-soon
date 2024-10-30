/* WordPress */
import { __ } from '@wordpress/i18n';
import { useContext } from '@wordpress/element';

/* Library */
import classNames from 'classnames';
import { cloneDeep } from 'lodash';

/*Atrc*/
import {
    AtrcText,
    AtrcControlToggle,
    AtrcWireFrameContentSidebar,
    AtrcWireFrameHeaderContentFooter,
    AtrcPrefix,
    AtrcPanelBody,
    AtrcPanelRow,
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

    const { deleteAll = false } = dbSettings;

    const updateSettingKey = (key, val) => {
        dbUpdateSetting(key, val);
    };

    return (
        <AtrcContent>
            <AtrcPanelRow>
                <AtrcControlToggle
                    label={__(
                        'Remove all plugin settings when deactivating.',
                        'acme-coming-soon'
                    )}
                    checked={deleteAll}
                    onChange={() => updateSettingKey('deleteAll', !deleteAll)}
                />
            </AtrcPanelRow>
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
                                localStorageClone.adDocs1 = !localStorageClone.adDocs1;
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
                            'What does "Remove all plugin settings when deactivating" do?',
                            'acme-coming-soon'
                        )}
                        initialOpen={true}>
                        <AtrcText
                            tag='p'
                            className={classNames(AtrcPrefix('m-0'), 'at-m')}>
                            {__(
                                'Enabling this option will erase all settings associated with the plugin from the WordPress options table. However, please note that the selected page will not be deleted from Pages.',
                                'acme-coming-soon'
                            )}
                        </AtrcText>
                    </AtrcPanelBody>
                    <AtrcPanelBody
                        title={__('Do I need to activate this option?', 'acme-coming-soon')}
                        initialOpen={false}>
                        <AtrcText
                            tag='p'
                            className={classNames(AtrcPrefix('m-0'), 'at-m')}>
                            {__(
                                'If you anticipate using the `Maintenance feature` in the future, no need to activate it.',
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

    const { adDocs1 } = lsSettings;

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
                        className: 'at-p',
                    }}
                    renderContent={<MainContent />}
                    renderSidebar={!adDocs1 ? <Documentation /> : null}
                    contentProps={{
                        contentCol: adDocs1 ? 'at-col-12' : 'at-col-7',
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
