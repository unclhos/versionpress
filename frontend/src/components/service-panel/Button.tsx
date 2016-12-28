import * as React from 'react';

import './Button.less';

interface ButtonProps {
  onClick(): void;
}

const Button: React.StatelessComponent<ButtonProps> = ({ onClick }) => (
  <button
    className='ServicePanelButton'
    onClick={onClick}
  >
    <span className='icon vp-icon-cog' />
  </button>
);

export default Button;
