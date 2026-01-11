import React, { useEffect, useRef } from 'react';
import { Image as KonvaImage } from 'react-konva';
import useImage from 'use-image';

const URLImage = ({ src, width, height, fit, filters, ...props }) => {
    const [image] = useImage(src, 'anonymous');
    const imageRef = useRef();

    useEffect(() => {
        if (image && imageRef.current) {
            if (filters && filters.length > 0) {
                imageRef.current.cache();
                imageRef.current.getLayer().batchDraw();
            } else {
                imageRef.current.clearCache();
            }
        }
    }, [image, width, height, filters]);

    if (!image) return null;

    let imgWidth = width;
    let imgHeight = height;
    let x = props.x || 0;
    let y = props.y || 0;

    if (fit === 'contain' && width && height) {
        const ratio = Math.min(width / image.width, height / image.height);
        imgWidth = image.width * ratio;
        imgHeight = image.height * ratio;
        x += (width - imgWidth) / 2;
        y += (height - imgHeight) / 2;
    }

    return (
        <KonvaImage
            ref={imageRef}
            image={image}
            width={imgWidth}
            height={imgHeight}
            filters={filters}
            {...props}
            x={x}
            y={y}
        />
    );
};

export default URLImage;
