import FormInputText from '../../components/FormInputText.vue';
import FormInputNumber from "../../components/FormInputNumber.vue";
import FormInputSwitcher from "../../components/FormInputSwitcher.vue";
import FormInputSelect from "../../components/FormInputSelect.vue";
import FormInputRepeater from "../../components/FormInputRepeater.vue";
import FormInputTextArea from "../../components/FormInputTextArea.vue";
import FormSettingsSwitcher from "../../components/FormSettingsSwitcher.vue";
import FormTabs from "../../components/FormTabs.vue";
import FormSection from "../../components/FormSection.vue";

export default {
    string: FormInputText,
    password: FormInputText,
    number: FormInputNumber,
    switcher: FormInputSwitcher,
    select: FormInputSelect,
    dropdown: FormInputSelect,
    repeater: FormInputRepeater,
    textarea: FormInputTextArea,
    settings_switcher: FormSettingsSwitcher,
    tabs: FormTabs,
    section: FormSection,
}
