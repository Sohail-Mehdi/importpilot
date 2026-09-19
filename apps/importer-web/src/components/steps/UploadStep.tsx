import React from 'react';
import { UploadCloud } from 'lucide-react';

export const UploadStep: React.FC = () => {
  return (
    <div className="upload-dropzone">
      <UploadCloud size={64} className="upload-icon" />
      <div className="upload-text">
        <h3 className="upload-title">Drag & Drop your file here</h3>
        <p className="upload-subtitle">or click to browse from your computer</p>
      </div>
      <p className="upload-subtitle" style={{ fontSize: '0.85rem', marginTop: '16px' }}>
        Supports .csv, .xlsx up to 50MB
      </p>
    </div>
  );
};
