import { memo } from '@wordpress/element';
import TextLayerControl from './TextLayerControl';
import ClipartLayerControl from './ClipartLayerControl';
import ImageLayerControl from './ImageLayerControl';

const LayerControl = (props) => {
    const { layer, personalisationMethod } = props;

    // Hide image/clipart controls for embroidery
    // if ((layer.type === 'image' || layer.type === 'clipart') && personalisationMethod === 'embroidery') {
    //     return null;
    // }

    if (layer.type === 'text') {
        return <TextLayerControl {...props} />;
    }

    if (layer.type === 'clipart') {
        return <ClipartLayerControl {...props} />;
    }

    // Default to Image Upload
    return <ImageLayerControl {...props} />;
};

export default memo(LayerControl);
